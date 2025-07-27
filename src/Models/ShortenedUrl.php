<?php

declare(strict_types=1);

namespace LinkShortener\Models;

use DateTime;
use JsonSerializable;

class ShortenedUrl implements JsonSerializable
{
    private ?int $id;
    private string $shortCode;
    private string $originalUrl;
    private ?string $partnerId;
    private ?string $customAlias;
    private ?string $title;
    private ?string $description;
    private DateTime $createdAt;
    private DateTime $updatedAt;
    private ?DateTime $expiresAt;
    private int $clickCount;
    private string $status;
    private ?array $metadata;
    private ?string $createdByIp;
    
    // Related objects
    private ?Partner $partner = null;
    private array $clickAnalytics = [];

    public function __construct(
        string $shortCode,
        string $originalUrl,
        ?string $partnerId = null,
        ?string $customAlias = null,
        ?string $title = null,
        ?string $description = null,
        ?DateTime $expiresAt = null,
        int $clickCount = 0,
        string $status = 'active',
        ?array $metadata = null,
        ?string $createdByIp = null,
        ?int $id = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->shortCode = $shortCode;
        $this->originalUrl = $originalUrl;
        $this->partnerId = $partnerId;
        $this->customAlias = $customAlias;
        $this->title = $title;
        $this->description = $description;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
        $this->expiresAt = $expiresAt;
        $this->clickCount = max(0, $clickCount);
        $this->status = $status;
        $this->metadata = $metadata ?? [];
        $this->createdByIp = $createdByIp;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function getPartnerId(): ?string
    {
        return $this->partnerId;
    }

    public function getCustomAlias(): ?string
    {
        return $this->customAlias;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedByIp(): ?string
    {
        return $this->createdByIp;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
        $this->touch();
    }

    public function setOriginalUrl(string $originalUrl): void
    {
        $this->originalUrl = $originalUrl;
        $this->touch();
    }

    public function setPartnerId(?string $partnerId): void
    {
        $this->partnerId = $partnerId;
        $this->touch();
    }

    public function setCustomAlias(?string $customAlias): void
    {
        $this->customAlias = $customAlias;
        $this->touch();
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function setExpiresAt(?DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
        $this->touch();
    }

    public function setClickCount(int $clickCount): void
    {
        $this->clickCount = max(0, $clickCount);
        $this->touch();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
        $this->touch();
    }

    public function setCreatedByIp(?string $createdByIp): void
    {
        $this->createdByIp = $createdByIp;
        $this->touch();
    }

    // Related objects
    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): void
    {
        $this->partner = $partner;
        if ($partner) {
            $this->partnerId = $partner->getId();
        }
    }

    public function getClickAnalytics(): array
    {
        return $this->clickAnalytics;
    }

    public function setClickAnalytics(array $clickAnalytics): void
    {
        $this->clickAnalytics = $clickAnalytics;
    }

    // Business logic methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }
        
        if ($this->expiresAt && $this->expiresAt < new DateTime()) {
            return true;
        }
        
        return false;
    }

    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    public function hasCustomAlias(): bool
    {
        return !empty($this->customAlias);
    }

    public function hasPartner(): bool
    {
        return !empty($this->partnerId);
    }

    public function hasExpiration(): bool
    {
        return $this->expiresAt !== null;
    }

    public function canBeAccessed(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    public function incrementClickCount(): void
    {
        $this->clickCount++;
        $this->touch();
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->touch();
    }

    public function disable(): void
    {
        $this->status = 'disabled';
        $this->touch();
    }

    public function expire(): void
    {
        $this->status = 'expired';
        $this->touch();
    }

    public function extendExpiration(DateTime $newExpiresAt): void
    {
        $this->expiresAt = $newExpiresAt;
        if ($this->status === 'expired') {
            $this->status = 'active';
        }
        $this->touch();
    }

    public function removeExpiration(): void
    {
        $this->expiresAt = null;
        if ($this->status === 'expired') {
            $this->status = 'active';
        }
        $this->touch();
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }
        
        $now = new DateTime();
        $diff = $now->diff($this->expiresAt);
        
        if ($this->expiresAt < $now) {
            return -$diff->days; // Negative for expired links
        }
        
        return $diff->days;
    }

    public function getHoursUntilExpiration(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }
        
        $now = new DateTime();
        $diff = $now->diff($this->expiresAt);
        
        $hours = ($diff->days * 24) + $diff->h;
        
        if ($this->expiresAt < $now) {
            return -$hours; // Negative for expired links
        }
        
        return $hours;
    }

    public function getShortUrl(string $baseUrl = ''): string
    {
        if (empty($baseUrl)) {
            $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        }
        
        return rtrim($baseUrl, '/') . '/' . $this->shortCode;
    }

    public function getDomain(): string
    {
        $parsed = parse_url($this->originalUrl);
        return $parsed['host'] ?? '';
    }

    public function getScheme(): string
    {
        $parsed = parse_url($this->originalUrl);
        return $parsed['scheme'] ?? 'http';
    }

    public function isHttps(): bool
    {
        return $this->getScheme() === 'https';
    }

    public function addMetadata(string $key, mixed $value): void
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        
        $this->metadata[$key] = $value;
        $this->touch();
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function removeMetadata(string $key): void
    {
        if ($this->metadata && isset($this->metadata[$key])) {
            unset($this->metadata[$key]);
            $this->touch();
        }
    }

    public function hasMetadata(string $key): bool
    {
        return $this->metadata && isset($this->metadata[$key]);
    }

    public function getAgeInDays(): int
    {
        $now = new DateTime();
        $diff = $now->diff($this->createdAt);
        return $diff->days;
    }

    public function getAgeInHours(): int
    {
        $now = new DateTime();
        $diff = $now->diff($this->createdAt);
        return ($diff->days * 24) + $diff->h;
    }

    public function isRecentlyCreated(int $hours = 24): bool
    {
        return $this->getAgeInHours() <= $hours;
    }

    public function getClickThroughRate(): float
    {
        // This would need to be calculated based on impressions vs clicks
        // For now, we'll return 0 as a placeholder
        return 0.0;
    }

    public function generateQrCode(): string
    {
        // This would generate a QR code for the short URL
        // Implementation would depend on the QR code library used
        return '';
    }

    // Static helper methods
    public static function generateShortCode(int $length = 6): string
    {
        $alphabet = $_ENV['SHORT_CODE_ALPHABET'] ?? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        
        return $code;
    }

    public static function generateShortCodeWithPartner(string $partnerId, int $length = 6): string
    {
        // Generate partner hash (0-999)
        $partnerHash = crc32($partnerId) % 1000;
        
        // Generate random URL ID
        $urlId = random_int(1, 1000000);
        
        // Encode with partner identification
        $encoded = base_convert(($urlId * 1000) + $partnerHash, 10, 36);
        
        // Pad to desired length
        return str_pad($encoded, $length, '0', STR_PAD_LEFT);
    }

    public static function extractPartnerFromShortCode(string $shortCode): ?string
    {
        try {
            $decoded = base_convert($shortCode, 36, 10);
            $partnerHash = $decoded % 1000;
            
            // This would need a lookup table to convert hash back to partner ID
            // For now, return null as this requires additional implementation
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    // Utility methods
    private function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'short_code' => $this->shortCode,
            'original_url' => $this->originalUrl,
            'partner_id' => $this->partnerId,
            'custom_alias' => $this->customAlias,
            'title' => $this->title,
            'description' => $this->description,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'click_count' => $this->clickCount,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_by_ip' => $this->createdByIp,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'has_custom_alias' => $this->hasCustomAlias(),
            'has_partner' => $this->hasPartner(),
            'has_expiration' => $this->hasExpiration(),
            'can_be_accessed' => $this->canBeAccessed(),
            'domain' => $this->getDomain(),
            'scheme' => $this->getScheme(),
            'is_https' => $this->isHttps(),
            'age_in_days' => $this->getAgeInDays(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'short_url' => $this->getShortUrl(),
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'short_code' => $this->shortCode,
            'original_url' => $this->originalUrl,
            'title' => $this->title,
            'description' => $this->description,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'click_count' => $this->clickCount,
            'status' => $this->status,
            'domain' => $this->getDomain(),
            'is_https' => $this->isHttps(),
            'short_url' => $this->getShortUrl(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toPublicArray();
    }
}