<?php

declare(strict_types=1);

namespace LinkShortener\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use LinkShortener\Config\Cache;
use InvalidArgumentException;

class UrlValidatorService
{
    private Client $httpClient;
    private array $blockedDomains;
    private array $allowedSchemes;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify' => true,
            'headers' => [
                'User-Agent' => 'LinkShortener/1.0'
            ]
        ]);

        $this->blockedDomains = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
            'bit.ly',
            'tinyurl.com',
            'short.link'
        ];

        $this->allowedSchemes = ['http', 'https'];
    }

    public function validateUrl(string $url): array
    {
        $result = [
            'valid' => false,
            'url' => $url,
            'normalized_url' => null,
            'errors' => []
        ];

        // Basic format validation
        if (!$this->isValidFormat($url)) {
            $result['errors'][] = 'Invalid URL format';
            return $result;
        }

        // Normalize URL
        $normalizedUrl = $this->normalizeUrl($url);
        $result['normalized_url'] = $normalizedUrl;

        // Parse URL components
        $parsedUrl = parse_url($normalizedUrl);
        if (!$parsedUrl) {
            $result['errors'][] = 'Unable to parse URL';
            return $result;
        }

        // Validate scheme
        if (!$this->isValidScheme($parsedUrl['scheme'] ?? '')) {
            $result['errors'][] = 'Invalid URL scheme. Only HTTP and HTTPS are allowed';
            return $result;
        }

        // Validate host
        if (!$this->isValidHost($parsedUrl['host'] ?? '')) {
            $result['errors'][] = 'Invalid or blocked host';
            return $result;
        }

        // Check if URL is reachable (with caching)
        if (!$this->isReachable($normalizedUrl)) {
            $result['errors'][] = 'URL is not reachable';
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }

    private function isValidFormat(string $url): bool
    {
        // Check for basic URL patterns
        if (empty($url) || strlen($url) > 2048) {
            return false;
        }

        // Check for spaces or invalid characters
        if (preg_match('/\s/', $url)) {
            return false;
        }

        // Basic URL regex
        $pattern = '/^https?:\/\/[^\s\/$.?#].[^\s]*$/i';
        return preg_match($pattern, $url) === 1;
    }

    private function normalizeUrl(string $url): string
    {
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        // Remove trailing slash
        $url = rtrim($url, '/');

        // Convert to lowercase for domain part
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $parsed['host'] = strtolower($parsed['host']);
            $url = $this->buildUrl($parsed);
        }

        return $url;
    }

    private function buildUrl(array $parsed): string
    {
        $url = $parsed['scheme'] . '://';
        
        if (isset($parsed['user'])) {
            $url .= $parsed['user'];
            if (isset($parsed['pass'])) {
                $url .= ':' . $parsed['pass'];
            }
            $url .= '@';
        }
        
        $url .= $parsed['host'];
        
        if (isset($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $url .= $parsed['path'];
        }
        
        if (isset($parsed['query'])) {
            $url .= '?' . $parsed['query'];
        }
        
        if (isset($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }
        
        return $url;
    }

    private function isValidScheme(string $scheme): bool
    {
        return in_array(strtolower($scheme), $this->allowedSchemes);
    }

    private function isValidHost(string $host): bool
    {
        if (empty($host)) {
            return false;
        }

        // Check against blocked domains
        foreach ($this->blockedDomains as $blockedDomain) {
            if (stripos($host, $blockedDomain) !== false) {
                return false;
            }
        }

        // Check if it's a valid domain or IP
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && 
            !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Check for private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private function isReachable(string $url): bool
    {
        $cacheKey = 'url_reachable:' . md5($url);
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached === '1';
        }

        try {
            $response = $this->httpClient->head($url, [
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => true
                ]
            ]);

            $isReachable = $response->getStatusCode() < 400;
            
            // Cache the result for 1 hour
            Cache::set($cacheKey, $isReachable ? '1' : '0', 3600);
            
            return $isReachable;
        } catch (RequestException $e) {
            // Cache negative results for shorter time (5 minutes)
            Cache::set($cacheKey, '0', 300);
            return false;
        }
    }

    public function sanitizeUrl(string $url): string
    {
        // Remove any potential XSS vectors
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        
        // Remove any javascript: or data: schemes
        $url = preg_replace('/^(javascript|data|vbscript):/i', '', $url);
        
        return trim($url);
    }

    public function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    public function isShortUrl(string $url): bool
    {
        $domain = $this->extractDomain($url);
        if (!$domain) {
            return false;
        }

        $shortUrlDomains = [
            'bit.ly', 'tinyurl.com', 'short.link', 'goo.gl', 't.co',
            'ow.ly', 'buff.ly', 'is.gd', 'v.gd', 'tiny.cc'
        ];

        return in_array(strtolower($domain), $shortUrlDomains);
    }
}