<?php

declare(strict_types=1);

namespace LinkShortener\Controllers;

use LinkShortener\Services\LinkService;
use LinkShortener\Services\SecurityService;
use DateTime;
use Monolog\Logger;

class LinkController
{
    private LinkService $linkService;
    private SecurityService $securityService;
    private Logger $logger;

    public function __construct(
        LinkService $linkService,
        SecurityService $securityService,
        Logger $logger
    ) {
        $this->linkService = $linkService;
        $this->securityService = $securityService;
        $this->logger = $logger;
    }

    public function createLink(): void
    {
        try {
            // Check rate limiting
            $clientIP = $this->securityService->getClientIP();
            $rateLimit = $this->securityService->checkRateLimit($clientIP, 'create_link');
            
            if (!$rateLimit['allowed']) {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Rate limit exceeded'
                ], 429, [
                    'X-RateLimit-Limit' => $_ENV['RATE_LIMIT_REQUESTS'],
                    'X-RateLimit-Remaining' => $rateLimit['remaining'],
                    'X-RateLimit-Reset' => $rateLimit['reset_time']
                ]);
                return;
            }

            // Check if IP is blocked
            if ($this->securityService->isIPBlocked($clientIP)) {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Access denied'
                ], 403);
                return;
            }

            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Method not allowed'
                ], 405);
                return;
            }

            // Get request data
            $input = $this->getRequestData();
            
            // Validate CSRF token
            if (!isset($input['csrf_token']) || !$this->securityService->validateCSRFToken($input['csrf_token'])) {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Invalid CSRF token'
                ], 403);
                return;
            }

            // Validate required fields
            if (empty($input['url'])) {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'URL is required'
                ], 400);
                return;
            }

            // Sanitize and validate inputs
            $url = $this->securityService->sanitizeInput($input['url']);
            $customCode = !empty($input['custom_code']) ? $this->securityService->sanitizeInput($input['custom_code']) : null;
            $expiresAt = !empty($input['expires_at']) ? new DateTime($input['expires_at']) : null;
            $userId = $this->getCurrentUserId();

            // Create short link
            $result = $this->linkService->createShortLink($url, $userId, $customCode, $expiresAt);

            if ($result['success']) {
                $link = $result['link'];
                $shortUrl = $this->buildShortUrl($link->getShortCode());
                
                $this->sendResponse([
                    'success' => true,
                    'data' => [
                        'short_code' => $link->getShortCode(),
                        'short_url' => $shortUrl,
                        'original_url' => $link->getOriginalUrl(),
                        'created_at' => $link->getCreatedAt()->format('Y-m-d H:i:s'),
                        'expires_at' => $link->getExpiresAt()?->format('Y-m-d H:i:s')
                    ],
                    'message' => $result['message'] ?? 'Short link created successfully'
                ], 201);
            } else {
                $this->sendResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in createLink', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function resolveLink(string $shortCode): void
    {
        try {
            $clientIP = $this->securityService->getClientIP();
            
            // Check rate limiting
            $rateLimit = $this->securityService->checkRateLimit($clientIP, 'resolve_link');
            
            if (!$rateLimit['allowed']) {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Rate limit exceeded'
                ], 429);
                return;
            }

            // Check if IP is blocked
            if ($this->securityService->isIPBlocked($clientIP)) {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Access denied'
                ], 403);
                return;
            }

            // Resolve short link
            $result = $this->linkService->resolveShortLink($shortCode);

            if ($result['success']) {
                // Redirect to original URL
                $originalUrl = $result['original_url'];
                
                // Set security headers
                $headers = $this->securityService->generateSecureHeaders();
                foreach ($headers as $header => $value) {
                    header($header . ': ' . $value);
                }

                // Perform redirect
                header('Location: ' . $originalUrl, true, 302);
                exit;
            } else {
                // Show error page or redirect to home
                $this->sendResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 404);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in resolveLink', [
                'error' => $e->getMessage(),
                'short_code' => $shortCode
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getUserLinks(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 per page

            $result = $this->linkService->getUserLinks($userId, $page, $limit);

            if ($result['success']) {
                $links = array_map(function($link) {
                    return [
                        'short_code' => $link->getShortCode(),
                        'short_url' => $this->buildShortUrl($link->getShortCode()),
                        'original_url' => $link->getOriginalUrl(),
                        'created_at' => $link->getCreatedAt()->format('Y-m-d H:i:s'),
                        'expires_at' => $link->getExpiresAt()?->format('Y-m-d H:i:s'),
                        'click_count' => $link->getClickCount(),
                        'is_active' => $link->isActive(),
                        'is_expired' => $link->isExpired()
                    ];
                }, $result['links']);

                $this->sendResponse([
                    'success' => true,
                    'data' => $links,
                    'pagination' => $result['pagination']
                ]);
            } else {
                $this->sendResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in getUserLinks', [
                'error' => $e->getMessage()
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function deleteLink(string $shortCode): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Method not allowed'
                ], 405);
                return;
            }

            $userId = $this->getCurrentUserId();
            $result = $this->linkService->deleteLink($shortCode, $userId);

            if ($result['success']) {
                $this->sendResponse([
                    'success' => true,
                    'message' => $result['message']
                ]);
            } else {
                $statusCode = $result['error'] === 'Unauthorized' ? 403 : 404;
                $this->sendResponse([
                    'success' => false,
                    'error' => $result['error']
                ], $statusCode);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in deleteLink', [
                'error' => $e->getMessage(),
                'short_code' => $shortCode
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getAnalytics(string $shortCode): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $result = $this->linkService->getAnalytics($shortCode, $userId);

            if ($result['success']) {
                $this->sendResponse([
                    'success' => true,
                    'data' => $result['analytics']
                ]);
            } else {
                $statusCode = $result['error'] === 'Unauthorized' ? 403 : 404;
                $this->sendResponse([
                    'success' => false,
                    'error' => $result['error']
                ], $statusCode);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in getAnalytics', [
                'error' => $e->getMessage(),
                'short_code' => $shortCode
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getSystemStats(): void
    {
        try {
            $result = $this->linkService->getSystemStats();

            if ($result['success']) {
                $this->sendResponse([
                    'success' => true,
                    'data' => $result['stats']
                ]);
            } else {
                $this->sendResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in getSystemStats', [
                'error' => $e->getMessage()
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getCSRFToken(): void
    {
        try {
            $token = $this->securityService->generateCSRFToken();
            
            $this->sendResponse([
                'success' => true,
                'data' => [
                    'csrf_token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error generating CSRF token', [
                'error' => $e->getMessage()
            ]);

            $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            return json_decode($json, true) ?? [];
        }
        
        return $_POST;
    }

    private function getCurrentUserId(): string
    {
        // In a real application, this would extract user ID from JWT token or session
        // For now, we'll use IP address as a simple identifier
        return $this->securityService->getClientIP();
    }

    private function buildShortUrl(string $shortCode): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return rtrim($baseUrl, '/') . '/' . $shortCode;
    }

    private function sendResponse(array $data, int $statusCode = 200, array $headers = []): void
    {
        // Set status code
        http_response_code($statusCode);
        
        // Set default headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Set security headers
        $securityHeaders = $this->securityService->generateSecureHeaders();
        foreach ($securityHeaders as $header => $value) {
            header($header . ': ' . $value);
        }
        
        // Set custom headers
        foreach ($headers as $header => $value) {
            header($header . ': ' . $value);
        }
        
        // Output JSON response
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}