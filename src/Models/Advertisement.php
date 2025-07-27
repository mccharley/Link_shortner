<?php

declare(strict_types=1);

namespace LinkShortener\Models;

use DateTime;
use JsonSerializable;

class Advertisement implements JsonSerializable
{
    private ?int $id;
    private string $title;
    private ?string $content;
    private ?string $imageUrl;
    private ?string $videoUrl;
    private ?string $clickUrl;
    private int $durationSeconds;
    private bool $active;
    private ?DateTime $startDate;
    private ?DateTime $endDate;
    private ?array $targetCountries;
    private ?array $targetDevices;
    private float $cpmRate;
    private ?float $dailyBudget;
    private ?float $totalBudget;
    private int $impressionsCount;
    private int $clicksCount;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $title,
        ?string $content = null,
        ?string $imageUrl = null,
        ?string $videoUrl = null,
        ?string $clickUrl = null,
        int $durationSeconds = 15,
        bool $active = true,
        ?DateTime $startDate = null,
        ?DateTime $endDate = null,
        ?array $targetCountries = null,
        ?array $targetDevices = null,
        float $cpmRate = 0.0000,
        ?float $dailyBudget = null,
        ?float $totalBudget = null,
        int $impressionsCount = 0,
        int $clicksCount = 0,
        ?int $id = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->imageUrl = $imageUrl;
        $this->videoUrl = $videoUrl;
        $this->clickUrl = $clickUrl;
        $this->durationSeconds = max(5, min(30, $durationSeconds)); // Clamp between 5-30 seconds
        $this->active = $active;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->targetCountries = $targetCountries;
        $this->targetDevices = $targetDevices;
        $this->cpmRate = max(0.0, $cpmRate);
        $this->dailyBudget = $dailyBudget ? max(0.0, $dailyBudget) : null;
        $this->totalBudget = $totalBudget ? max(0.0, $totalBudget) : null;
        $this->impressionsCount = max(0, $impressionsCount);
        $this->clicksCount = max(0, $clicksCount);
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function getClickUrl(): ?string
    {
        return $this->clickUrl;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function getTargetCountries(): ?array
    {
        return $this->targetCountries;
    }

    public function getTargetDevices(): ?array
    {
        return $this->targetDevices;
    }

    public function getCpmRate(): float
    {
        return $this->cpmRate;
    }

    public function getDailyBudget(): ?float
    {
        return $this->dailyBudget;
    }

    public function getTotalBudget(): ?float
    {
        return $this->totalBudget;
    }

    public function getImpressionsCount(): int
    {
        return $this->impressionsCount;
    }

    public function getClicksCount(): int
    {
        return $this->clicksCount;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
        $this->touch();
    }

    public function setImageUrl(?string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
        $this->touch();
    }

    public function setVideoUrl(?string $videoUrl): void
    {
        $this->videoUrl = $videoUrl;
        $this->touch();
    }

    public function setClickUrl(?string $clickUrl): void
    {
        $this->clickUrl = $clickUrl;
        $this->touch();
    }

    public function setDurationSeconds(int $durationSeconds): void
    {
        $this->durationSeconds = max(5, min(30, $durationSeconds));
        $this->touch();
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->touch();
    }

    public function setStartDate(?DateTime $startDate): void
    {
        $this->startDate = $startDate;
        $this->touch();
    }

    public function setEndDate(?DateTime $endDate): void
    {
        $this->endDate = $endDate;
        $this->touch();
    }

    public function setTargetCountries(?array $targetCountries): void
    {
        $this->targetCountries = $targetCountries;
        $this->touch();
    }

    public function setTargetDevices(?array $targetDevices): void
    {
        $this->targetDevices = $targetDevices;
        $this->touch();
    }

    public function setCpmRate(float $cpmRate): void
    {
        $this->cpmRate = max(0.0, $cpmRate);
        $this->touch();
    }

    public function setDailyBudget(?float $dailyBudget): void
    {
        $this->dailyBudget = $dailyBudget ? max(0.0, $dailyBudget) : null;
        $this->touch();
    }

    public function setTotalBudget(?float $totalBudget): void
    {
        $this->totalBudget = $totalBudget ? max(0.0, $totalBudget) : null;
        $this->touch();
    }

    public function setImpressionsCount(int $impressionsCount): void
    {
        $this->impressionsCount = max(0, $impressionsCount);
        $this->touch();
    }

    public function setClicksCount(int $clicksCount): void
    {
        $this->clicksCount = max(0, $clicksCount);
        $this->touch();
    }

    // Business logic methods
    public function isCurrentlyActive(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = new DateTime();

        if ($this->startDate && $now < $this->startDate) {
            return false;
        }

        if ($this->endDate && $now > $this->endDate) {
            return false;
        }

        return true;
    }

    public function isScheduled(): bool
    {
        return $this->startDate && $this->startDate > new DateTime();
    }

    public function isExpired(): bool
    {
        return $this->endDate && $this->endDate < new DateTime();
    }

    public function hasVideo(): bool
    {
        return !empty($this->videoUrl);
    }

    public function hasImage(): bool
    {
        return !empty($this->imageUrl);
    }

    public function hasClickAction(): bool
    {
        return !empty($this->clickUrl);
    }

    public function getClickThroughRate(): float
    {
        if ($this->impressionsCount === 0) {
            return 0.0;
        }

        return round(($this->clicksCount / $this->impressionsCount) * 100, 2);
    }

    public function getTotalRevenue(): float
    {
        return ($this->impressionsCount / 1000) * $this->cpmRate;
    }

    public function getAverageCpc(): float
    {
        if ($this->clicksCount === 0) {
            return 0.0;
        }

        return round($this->getTotalRevenue() / $this->clicksCount, 4);
    }

    public function incrementImpressions(): void
    {
        $this->impressionsCount++;
        $this->touch();
    }

    public function incrementClicks(): void
    {
        $this->clicksCount++;
        $this->touch();
    }

    public function isTargetedToCountry(string $countryCode): bool
    {
        if ($this->targetCountries === null || empty($this->targetCountries)) {
            return true; // No targeting means all countries
        }

        return in_array(strtoupper($countryCode), array_map('strtoupper', $this->targetCountries));
    }

    public function isTargetedToDevice(string $deviceType): bool
    {
        if ($this->targetDevices === null || empty($this->targetDevices)) {
            return true; // No targeting means all devices
        }

        return in_array(strtolower($deviceType), array_map('strtolower', $this->targetDevices));
    }

    public function activate(): void
    {
        $this->active = true;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->touch();
    }

    public function pause(): void
    {
        $this->deactivate();
    }

    public function resume(): void
    {
        $this->activate();
    }

    public function addTargetCountry(string $countryCode): void
    {
        if ($this->targetCountries === null) {
            $this->targetCountries = [];
        }

        $countryCode = strtoupper($countryCode);
        if (!in_array($countryCode, $this->targetCountries)) {
            $this->targetCountries[] = $countryCode;
            $this->touch();
        }
    }

    public function removeTargetCountry(string $countryCode): void
    {
        if ($this->targetCountries === null) {
            return;
        }

        $countryCode = strtoupper($countryCode);
        $index = array_search($countryCode, $this->targetCountries);
        if ($index !== false) {
            unset($this->targetCountries[$index]);
            $this->targetCountries = array_values($this->targetCountries); // Reindex
            $this->touch();
        }
    }

    public function addTargetDevice(string $deviceType): void
    {
        if ($this->targetDevices === null) {
            $this->targetDevices = [];
        }

        $deviceType = strtolower($deviceType);
        if (!in_array($deviceType, $this->targetDevices)) {
            $this->targetDevices[] = $deviceType;
            $this->touch();
        }
    }

    public function removeTargetDevice(string $deviceType): void
    {
        if ($this->targetDevices === null) {
            return;
        }

        $deviceType = strtolower($deviceType);
        $index = array_search($deviceType, $this->targetDevices);
        if ($index !== false) {
            unset($this->targetDevices[$index]);
            $this->targetDevices = array_values($this->targetDevices); // Reindex
            $this->touch();
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
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $this->imageUrl,
            'video_url' => $this->videoUrl,
            'click_url' => $this->clickUrl,
            'duration_seconds' => $this->durationSeconds,
            'active' => $this->active,
            'start_date' => $this->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate?->format('Y-m-d H:i:s'),
            'target_countries' => $this->targetCountries,
            'target_devices' => $this->targetDevices,
            'cpm_rate' => $this->cpmRate,
            'daily_budget' => $this->dailyBudget,
            'total_budget' => $this->totalBudget,
            'impressions_count' => $this->impressionsCount,
            'clicks_count' => $this->clicksCount,
            'click_through_rate' => $this->getClickThroughRate(),
            'total_revenue' => $this->getTotalRevenue(),
            'average_cpc' => $this->getAverageCpc(),
            'currently_active' => $this->isCurrentlyActive(),
            'is_scheduled' => $this->isScheduled(),
            'is_expired' => $this->isExpired(),
            'has_video' => $this->hasVideo(),
            'has_image' => $this->hasImage(),
            'has_click_action' => $this->hasClickAction(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}