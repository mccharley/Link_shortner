<?php

declare(strict_types=1);

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use LinkShortener\Controllers\LinkController;
use LinkShortener\Services\LinkService;
use LinkShortener\Services\UrlValidatorService;
use LinkShortener\Services\ShortCodeGeneratorService;
use LinkShortener\Services\SecurityService;
use LinkShortener\Repositories\LinkRepository;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');

// Set up logging
$logger = new Logger('linkshortener');
$logFile = $_ENV['LOG_FILE'] ?? 'logs/app.log';
$logLevel = $_ENV['LOG_LEVEL'] ?? 'info';

// Create logs directory if it doesn't exist
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger->pushHandler(new RotatingFileHandler($logFile, 0, Logger::toMonologLevel($logLevel)));

// Set up error handler
set_error_handler(function($severity, $message, $file, $line) use ($logger) {
    $logger->error("PHP Error: $message", [
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
});

set_exception_handler(function($exception) use ($logger) {
    $logger->critical("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    http_response_code(500);
    
    if ($_ENV['APP_DEBUG'] === 'true') {
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['error' => 'Internal Server Error']);
    }
});

// Dependency injection container
class Container
{
    private array $services = [];
    private array $singletons = [];

    public function set(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }

    public function setSingleton(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
        $this->singletons[$name] = true;
    }

    public function get(string $name)
    {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '$name' not found");
        }

        if (isset($this->singletons[$name])) {
            static $instances = [];
            if (!isset($instances[$name])) {
                $instances[$name] = $this->services[$name]($this);
            }
            return $instances[$name];
        }

        return $this->services[$name]($this);
    }
}

// Set up dependency injection
$container = new Container();

// Register services
$container->setSingleton('logger', fn() => $logger);

$container->setSingleton('linkRepository', fn() => new LinkRepository());

$container->setSingleton('urlValidator', fn() => new UrlValidatorService());

$container->setSingleton('shortCodeGenerator', fn($c) => new ShortCodeGeneratorService($c->get('linkRepository')));

$container->setSingleton('securityService', fn() => new SecurityService());

$container->setSingleton('linkService', fn($c) => new LinkService(
    $c->get('linkRepository'),
    $c->get('urlValidator'),
    $c->get('shortCodeGenerator'),
    $c->get('securityService'),
    $c->get('logger')
));

$container->setSingleton('linkController', fn($c) => new LinkController(
    $c->get('linkService'),
    $c->get('securityService'),
    $c->get('logger')
));

// Simple router
class Router
{
    private array $routes = [];

    public function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches); // Remove full match
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}

// Set up routes
$router = new Router();

// API routes
$router->addRoute('POST', '#^/api/links$#', function() use ($container) {
    $container->get('linkController')->createLink();
});

$router->addRoute('GET', '#^/api/links$#', function() use ($container) {
    $container->get('linkController')->getUserLinks();
});

$router->addRoute('GET', '#^/api/links/([a-zA-Z0-9]+)$#', function($shortCode) use ($container) {
    $container->get('linkController')->getAnalytics($shortCode);
});

$router->addRoute('DELETE', '#^/api/links/([a-zA-Z0-9]+)$#', function($shortCode) use ($container) {
    $container->get('linkController')->deleteLink($shortCode);
});

$router->addRoute('GET', '#^/api/stats$#', function() use ($container) {
    $container->get('linkController')->getSystemStats();
});

$router->addRoute('GET', '#^/api/csrf-token$#', function() use ($container) {
    $container->get('linkController')->getCSRFToken();
});

// Short link resolution
$router->addRoute('GET', '#^/([a-zA-Z0-9]+)$#', function($shortCode) use ($container) {
    $container->get('linkController')->resolveLink($shortCode);
});

// Serve static frontend for root path
$router->addRoute('GET', '#^/$#', function() {
    readfile(__DIR__ . '/app.html');
});

// Health check endpoint
$router->addRoute('GET', '#^/health$#', function() {
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '2.0.0'
    ]);
});

// Get request path
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Handle CORS for API requests
if (strpos($requestPath, '/api/') === 0) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    if ($requestMethod === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Dispatch request
try {
    $router->dispatch($requestMethod, $requestPath);
} catch (Exception $e) {
    $logger->error('Router error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}