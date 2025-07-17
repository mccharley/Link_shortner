<?php

declare(strict_types=1);

namespace LinkShortener\Services;

use LinkShortener\Config\Cache;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class SecurityService
{
    private string $jwtSecret;
    private int $csrfTokenLifetime;
    private int $rateLimitRequests;
    private int $rateLimitWindow;

    public function __construct()
    {
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default-secret-change-this';
        $this->csrfTokenLifetime = (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600);
        $this->rateLimitRequests = (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $this->rateLimitWindow = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 3600);
    }

    public function generateCSRFToken(): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'localhost',
            'iat' => time(),
            'exp' => time() + $this->csrfTokenLifetime,
            'type' => 'csrf',
            'nonce' => bin2hex(random_bytes(16))
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function validateCSRFToken(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Check if it's a CSRF token
            if (!isset($decoded->type) || $decoded->type !== 'csrf') {
                return false;
            }

            return true;
        } catch (ExpiredException | SignatureInvalidException $e) {
            return false;
        }
    }

    public function checkRateLimit(string $identifier, string $action = 'default'): array
    {
        $key = "rate_limit:{$action}:{$identifier}";
        $current = Cache::get($key);
        
        if ($current === null) {
            // First request
            Cache::set($key, '1', $this->rateLimitWindow);
            return [
                'allowed' => true,
                'requests' => 1,
                'remaining' => $this->rateLimitRequests - 1,
                'reset_time' => time() + $this->rateLimitWindow
            ];
        }

        $requests = (int)$current;
        
        if ($requests >= $this->rateLimitRequests) {
            return [
                'allowed' => false,
                'requests' => $requests,
                'remaining' => 0,
                'reset_time' => time() + $this->rateLimitWindow
            ];
        }

        // Increment counter
        $newCount = Cache::increment($key);
        
        return [
            'allowed' => true,
            'requests' => $newCount,
            'remaining' => max(0, $this->rateLimitRequests - $newCount),
            'reset_time' => time() + $this->rateLimitWindow
        ];
    }

    public function sanitizeInput(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove control characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }

    public function sanitizeHtml(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function validateInput(string $input, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $rule => $value) {
            switch ($rule) {
                case 'required':
                    if ($value && empty($input)) {
                        $errors[] = 'This field is required';
                    }
                    break;
                    
                case 'min_length':
                    if (strlen($input) < $value) {
                        $errors[] = "Minimum length is {$value} characters";
                    }
                    break;
                    
                case 'max_length':
                    if (strlen($input) > $value) {
                        $errors[] = "Maximum length is {$value} characters";
                    }
                    break;
                    
                case 'pattern':
                    if (!preg_match($value, $input)) {
                        $errors[] = 'Invalid format';
                    }
                    break;
                    
                case 'url':
                    if ($value && !filter_var($input, FILTER_VALIDATE_URL)) {
                        $errors[] = 'Invalid URL format';
                    }
                    break;
                    
                case 'email':
                    if ($value && !filter_var($input, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Invalid email format';
                    }
                    break;
            }
        }
        
        return $errors;
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public function generateApiKey(): string
    {
        return 'lnk_' . bin2hex(random_bytes(20));
    }

    public function validateApiKey(string $apiKey): bool
    {
        // Check format
        if (!preg_match('/^lnk_[a-f0-9]{40}$/', $apiKey)) {
            return false;
        }
        
        // Check if key exists in cache/database
        $cacheKey = 'api_key:' . $apiKey;
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached === '1';
        }
        
        // In a real implementation, you would check against a database
        // For now, we'll just validate the format
        return true;
    }

    public function encryptData(string $data): string
    {
        $key = hash('sha256', $this->jwtSecret, true);
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    public function decryptData(string $encryptedData): ?string
    {
        $key = hash('sha256', $this->jwtSecret, true);
        $data = base64_decode($encryptedData);
        
        if (strlen($data) < 16) {
            return null;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        return $decrypted !== false ? $decrypted : null;
    }

    public function logSecurityEvent(string $event, array $context = []): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context
        ];
        
        // In a real implementation, you would log to a file or database
        error_log('SECURITY: ' . json_encode($logData));
    }

    public function detectSuspiciousActivity(string $identifier): bool
    {
        $suspiciousPatterns = [
            'multiple_failures' => 'failed_attempts:' . $identifier,
            'rapid_requests' => 'rapid_requests:' . $identifier,
            'unusual_patterns' => 'unusual_patterns:' . $identifier
        ];
        
        foreach ($suspiciousPatterns as $pattern => $key) {
            $count = Cache::get($key);
            if ($count !== null && (int)$count > 10) {
                $this->logSecurityEvent('suspicious_activity_detected', [
                    'pattern' => $pattern,
                    'identifier' => $identifier,
                    'count' => $count
                ]);
                return true;
            }
        }
        
        return false;
    }

    public function blockSuspiciousIP(string $ip, int $duration = 3600): void
    {
        $key = 'blocked_ip:' . $ip;
        Cache::set($key, '1', $duration);
        
        $this->logSecurityEvent('ip_blocked', [
            'ip' => $ip,
            'duration' => $duration
        ]);
    }

    public function isIPBlocked(string $ip): bool
    {
        $key = 'blocked_ip:' . $ip;
        return Cache::get($key) === '1';
    }

    public function getClientIP(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public function generateSecureHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];
    }
}