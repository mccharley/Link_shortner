<?php

declare(strict_types=1);

namespace LinkShortener\Services;

use LinkShortener\Models\Link;
use LinkShortener\Repositories\LinkRepository;
use LinkShortener\Config\Cache;
use DateTime;
use Monolog\Logger;

class LinkService
{
    private LinkRepository $linkRepository;
    private UrlValidatorService $urlValidator;
    private ShortCodeGeneratorService $shortCodeGenerator;
    private SecurityService $securityService;
    private Logger $logger;

    public function __construct(
        LinkRepository $linkRepository,
        UrlValidatorService $urlValidator,
        ShortCodeGeneratorService $shortCodeGenerator,
        SecurityService $securityService,
        Logger $logger
    ) {
        $this->linkRepository = $linkRepository;
        $this->urlValidator = $urlValidator;
        $this->shortCodeGenerator = $shortCodeGenerator;
        $this->securityService = $securityService;
        $this->logger = $logger;
    }

    public function createShortLink(string $originalUrl, string $createdBy, ?string $customCode = null, ?DateTime $expiresAt = null): array
    {
        try {
            // Sanitize input
            $originalUrl = $this->securityService->sanitizeInput($originalUrl);
            $createdBy = $this->securityService->sanitizeInput($createdBy);
            
            // Validate URL
            $validation = $this->urlValidator->validateUrl($originalUrl);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid URL: ' . implode(', ', $validation['errors'])
                ];
            }

            $normalizedUrl = $validation['normalized_url'];

            // Check if URL already exists
            $existingLink = $this->linkRepository->findByOriginalUrl($normalizedUrl);
            if ($existingLink && $existingLink->isActive() && !$existingLink->isExpired()) {
                return [
                    'success' => true,
                    'link' => $existingLink,
                    'message' => 'URL already exists'
                ];
            }

            // Generate short code
            if ($customCode) {
                $shortCode = $this->shortCodeGenerator->generateCustomShortCode($customCode);
            } else {
                $shortCode = $this->shortCodeGenerator->generateUniqueShortCode();
            }

            // Create link
            $link = new Link(
                $normalizedUrl,
                $shortCode,
                $createdBy,
                null,
                new DateTime(),
                $expiresAt
            );

            // Add metadata
            $link->addMetadata('domain', $this->urlValidator->extractDomain($normalizedUrl));
            $link->addMetadata('created_ip', $this->securityService->getClientIP());
            $link->addMetadata('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

            // Save to database
            if ($this->linkRepository->save($link)) {
                // Cache the link for faster retrieval
                $this->cacheLink($link);
                
                $this->logger->info('Short link created', [
                    'short_code' => $shortCode,
                    'original_url' => $normalizedUrl,
                    'created_by' => $createdBy
                ]);

                return [
                    'success' => true,
                    'link' => $link
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to save link'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error creating short link', [
                'error' => $e->getMessage(),
                'original_url' => $originalUrl ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error'
            ];
        }
    }

    public function resolveShortLink(string $shortCode): array
    {
        try {
            // Validate short code format
            if (!$this->shortCodeGenerator->validateShortCode($shortCode)) {
                return [
                    'success' => false,
                    'error' => 'Invalid short code format'
                ];
            }

            // Try cache first
            $link = $this->getCachedLink($shortCode);
            
            if (!$link) {
                // Fallback to database
                $link = $this->linkRepository->findByShortCode($shortCode);
                
                if (!$link) {
                    return [
                        'success' => false,
                        'error' => 'Short link not found'
                    ];
                }
                
                // Cache for future requests
                $this->cacheLink($link);
            }

            // Check if link is active and not expired
            if (!$link->isActive()) {
                return [
                    'success' => false,
                    'error' => 'Short link is disabled'
                ];
            }

            if ($link->isExpired()) {
                return [
                    'success' => false,
                    'error' => 'Short link has expired'
                ];
            }

            // Increment click count asynchronously
            $this->incrementClickCount($shortCode);

            return [
                'success' => true,
                'link' => $link,
                'original_url' => $link->getOriginalUrl()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error resolving short link', [
                'error' => $e->getMessage(),
                'short_code' => $shortCode
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error'
            ];
        }
    }

    public function getUserLinks(string $userId, int $page = 1, int $limit = 20): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $links = $this->linkRepository->findByCreatedBy($userId, $limit, $offset);

            return [
                'success' => true,
                'links' => $links,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($links)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error fetching user links', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error'
            ];
        }
    }

    public function deleteLink(string $shortCode, string $userId): array
    {
        try {
            $link = $this->linkRepository->findByShortCode($shortCode);
            
            if (!$link) {
                return [
                    'success' => false,
                    'error' => 'Short link not found'
                ];
            }

            // Check ownership
            if ($link->getCreatedBy() !== $userId) {
                return [
                    'success' => false,
                    'error' => 'Unauthorized'
                ];
            }

            // Soft delete
            if ($this->linkRepository->deleteByShortCode($shortCode)) {
                // Remove from cache
                $this->removeCachedLink($shortCode);
                
                $this->logger->info('Short link deleted', [
                    'short_code' => $shortCode,
                    'user_id' => $userId
                ]);

                return [
                    'success' => true,
                    'message' => 'Link deleted successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to delete link'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error deleting link', [
                'error' => $e->getMessage(),
                'short_code' => $shortCode,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error'
            ];
        }
    }

    public function getAnalytics(string $shortCode, string $userId): array
    {
        try {
            $link = $this->linkRepository->findByShortCode($shortCode);
            
            if (!$link) {
                return [
                    'success' => false,
                    'error' => 'Short link not found'
                ];
            }

            // Check ownership
            if ($link->getCreatedBy() !== $userId) {
                return [
                    'success' => false,
                    'error' => 'Unauthorized'
                ];
            }

            $analytics = [
                'short_code' => $shortCode,
                'original_url' => $link->getOriginalUrl(),
                'created_at' => $link->getCreatedAt()->format('Y-m-d H:i:s'),
                'expires_at' => $link->getExpiresAt()?->format('Y-m-d H:i:s'),
                'click_count' => $link->getClickCount(),
                'is_active' => $link->isActive(),
                'is_expired' => $link->isExpired(),
                'metadata' => $link->getMetadata()
            ];

            return [
                'success' => true,
                'analytics' => $analytics
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error fetching analytics', [
                'error' => $e->getMessage(),
                'short_code' => $shortCode,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error'
            ];
        }
    }

    public function getSystemStats(): array
    {
        try {
            $stats = $this->linkRepository->getStats();
            
            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error fetching system stats', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error'
            ];
        }
    }

    public function cleanupExpiredLinks(): int
    {
        try {
            $count = $this->linkRepository->cleanupExpiredLinks();
            
            $this->logger->info('Expired links cleaned up', [
                'count' => $count
            ]);

            return $count;

        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up expired links', [
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    private function cacheLink(Link $link): void
    {
        $cacheKey = 'link:' . $link->getShortCode();
        $data = json_encode($link->toArray());
        $ttl = (int)($_ENV['CACHE_TTL'] ?? 3600);
        
        Cache::set($cacheKey, $data, $ttl);
    }

    private function getCachedLink(string $shortCode): ?Link
    {
        $cacheKey = 'link:' . $shortCode;
        $data = Cache::get($cacheKey);
        
        if ($data === null) {
            return null;
        }

        $linkData = json_decode($data, true);
        if (!$linkData) {
            return null;
        }

        $createdAt = new DateTime($linkData['created_at']);
        $expiresAt = $linkData['expires_at'] ? new DateTime($linkData['expires_at']) : null;
        $metadata = json_decode($linkData['metadata'] ?? '{}', true);

        return new Link(
            $linkData['original_url'],
            $linkData['short_code'],
            $linkData['created_by'],
            $linkData['id'],
            $createdAt,
            $expiresAt,
            $linkData['click_count'],
            $linkData['is_active'],
            $metadata
        );
    }

    private function removeCachedLink(string $shortCode): void
    {
        $cacheKey = 'link:' . $shortCode;
        Cache::delete($cacheKey);
    }

    private function incrementClickCount(string $shortCode): void
    {
        // Increment in database
        $this->linkRepository->incrementClickCount($shortCode);
        
        // Update cache
        $link = $this->getCachedLink($shortCode);
        if ($link) {
            $link->incrementClickCount();
            $this->cacheLink($link);
        }
    }
}