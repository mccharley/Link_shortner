<?php

declare(strict_types=1);

namespace LinkShortener\Models;

use DateTime;

class Link
{
    private ?int $id;
    private string $originalUrl;
    private string $shortCode;
    private DateTime $createdAt;
    private ?DateTime $expiresAt;
    private string $createdBy;
    private int $clickCount;
    private bool $isActive;
    private array $metadata;

    public function __construct(
        string $originalUrl,
        string $shortCode,
        string $createdBy,
        ?int $id = null,
        ?DateTime $createdAt = null,
        ?DateTime $expiresAt = null,
        int $clickCount = 0,
        bool $isActive = true,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->originalUrl = $originalUrl;
        $this->shortCode = $shortCode;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->expiresAt = $expiresAt;
        $this->clickCount = $clickCount;
        $this->isActive = $isActive;
        $this->metadata = $metadata;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function setOriginalUrl(string $originalUrl): void
    {
        $this->originalUrl = $originalUrl;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function setClickCount(int $clickCount): void
    {
        $this->clickCount = $clickCount;
    }

    public function incrementClickCount(): void
    {
        $this->clickCount++;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        
        return $this->expiresAt < new DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_url' => $this->originalUrl,
            'short_code' => $this->shortCode,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy,
            'click_count' => $this->clickCount,
            'is_active' => $this->isActive,
            'metadata' => json_encode($this->metadata)
        ];
    }
}