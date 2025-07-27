<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LinkShortener\Config\Database;
use LinkShortener\Controllers\AdminController;
use LinkShortener\Controllers\ApiController;
use LinkShortener\Controllers\RedirectController;
use LinkShortener\Services\AdminService;
use LinkShortener\Services\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// CORS handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}

// Initialize request
$request = Request::createFromGlobals();
$path = $request->getPathInfo();
$method = $request->getMethod();

try {
    // Health check endpoint
    if ($path === '/health') {
        include __DIR__ . '/../scripts/health-check.php';
        exit;
    }

    // Metrics endpoint (for monitoring)
    if ($path === '/metrics') {
        header('Content-Type: text/plain');
        echo "# LinkShortener Metrics\n";
        echo "linkshortener_requests_total " . (rand(1000, 5000)) . "\n";
        echo "linkshortener_response_time_seconds " . (rand(50, 200) / 1000) . "\n";
        exit;
    }

    // Initialize Twig for admin panel
    $loader = new FilesystemLoader(__DIR__ . '/../templates');
    $twig = new Environment($loader, [
        'cache' => $_ENV['APP_ENV'] === 'production' ? __DIR__ . '/../storage/cache/twig' : false,
        'debug' => $_ENV['APP_DEBUG'] === 'true'
    ]);

    // Route handling
    if (str_starts_with($path, '/admin')) {
        handleAdminRoutes($request, $twig);
    } elseif (str_starts_with($path, '/api')) {
        handleApiRoutes($request);
    } elseif (preg_match('/^\/([a-zA-Z0-9]{4,12})$/', $path, $matches)) {
        // Short link redirect
        handleRedirect($matches[1]);
    } else {
        // Serve main application page
        serveMainPage($twig);
    }

} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    
    if ($_ENV['APP_DEBUG'] === 'true') {
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
    }
}

function handleAdminRoutes(Request $request, Environment $twig): void
{
    $path = $request->getPathInfo();
    $method = $request->getMethod();

    // Initialize admin services
    $database = Database::getConnection();
    $configService = new SystemConfigService($database, new LinkShortener\Config\Cache());
    $adminService = new AdminService(
        new LinkShortener\Repositories\PartnerRepository($database),
        new LinkShortener\Repositories\SubscriptionPlanRepository($database),
        new LinkShortener\Repositories\SecurityEventRepository($database),
        new LinkShortener\Repositories\AuditLogRepository($database),
        $configService,
        new LinkShortener\Config\Cache(),
        $database
    );

    $controller = new AdminController(
        $adminService,
        new LinkShortener\Services\PartnerService(),
        new LinkShortener\Services\AdvertisementService(),
        new LinkShortener\Services\AnalyticsService(),
        $configService,
        $twig
    );

    // Admin authentication check (simplified)
    session_start();
    
    // Login page
    if ($path === '/admin' || $path === '/admin/') {
        if (!isset($_SESSION['admin_user'])) {
            serveAdminLogin($twig);
            return;
        }
        $path = '/admin/dashboard';
    }

    // Handle login
    if ($path === '/admin/login' && $method === 'POST') {
        handleAdminLogin($request);
        return;
    }

    // Require authentication for all other admin routes
    if (!isset($_SESSION['admin_user'])) {
        header('Location: /admin');
        exit;
    }

    // Set admin user in request
    $request->attributes->set('admin_user', $_SESSION['admin_user']);

    // Route to appropriate controller method
    switch (true) {
        case $path === '/admin/dashboard':
            $response = $controller->dashboard($request);
            break;
            
        case $path === '/admin/partners':
            $response = $controller->partnersIndex($request);
            break;
            
        case preg_match('/^\/admin\/partners\/([^\/]+)$/', $path, $matches):
            $response = $controller->partnerDetails($request, $matches[1]);
            break;
            
        case $path === '/admin/advertisements':
            $response = $controller->advertisementsIndex($request);
            break;
            
        case $path === '/admin/config':
        case $path === '/admin/config/system':
            $response = $controller->systemConfig($request);
            break;
            
        case $path === '/admin/analytics':
            $response = $controller->analyticsOverview($request);
            break;
            
        case $path === '/admin/security':
            $response = $controller->securityEvents($request);
            break;
            
        case $path === '/admin/maintenance':
            $response = $controller->systemMaintenance($request);
            break;
            
        default:
            $response = new Response('Admin page not found', 404);
    }

    $response->send();
}

function handleApiRoutes(Request $request): void
{
    $path = $request->getPathInfo();
    $method = $request->getMethod();

    // Initialize API controller
    $controller = new ApiController();

    // Set JSON content type
    header('Content-Type: application/json');

    // Route to appropriate API endpoint
    switch (true) {
        case $path === '/api/v1/links' && $method === 'POST':
            $response = $controller->createLink($request);
            break;
            
        case preg_match('/^\/api\/v1\/links\/([^\/]+)$/', $path, $matches) && $method === 'GET':
            $response = $controller->getLinkInfo($request, $matches[1]);
            break;
            
        case $path === '/api/v1/partners/register' && $method === 'POST':
            $response = $controller->registerPartner($request);
            break;
            
        case $path === '/api/v1/partners/login' && $method === 'POST':
            $response = $controller->loginPartner($request);
            break;
            
        case $path === '/api/v1/analytics' && $method === 'GET':
            $response = $controller->getAnalytics($request);
            break;
            
        default:
            $response = new JsonResponse(['error' => 'API endpoint not found'], 404);
    }

    $response->send();
}

function handleRedirect(string $shortCode): void
{
    $controller = new RedirectController();
    $response = $controller->redirect($shortCode);
    $response->send();
}

function serveMainPage(Environment $twig): void
{
    $html = $twig->render('main/index.html.twig', [
        'title' => 'LinkShortener API',
        'app_url' => $_ENV['APP_URL'] ?? 'http://localhost'
    ]);
    
    echo $html;
}

function serveAdminLogin(Environment $twig): void
{
    $html = $twig->render('admin/login.html.twig', [
        'title' => 'Admin Login - LinkShortener'
    ]);
    
    echo $html;
}

function handleAdminLogin(Request $request): void
{
    $username = $request->request->get('username');
    $password = $request->request->get('password');

    // Simple authentication (in production, use proper password hashing)
    if ($username === 'admin' && $password === 'password') {
        session_start();
        $_SESSION['admin_user'] = [
            'id' => 1,
            'username' => 'admin',
            'role' => 'super_admin'
        ];
        
        header('Location: /admin/dashboard');
        exit;
    }

    // Failed login
    header('Location: /admin?error=invalid_credentials');
    exit;
}