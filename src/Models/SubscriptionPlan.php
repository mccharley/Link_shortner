<?php

declare(strict_types=1);

namespace LinkShortener\Models;

use DateTime;
use JsonSerializable;

class SubscriptionPlan implements JsonSerializable
{
    private string $id;
    private string $name;
    private ?string $description;
    private float $priceMonthly;
    private float $priceYearly;
    private int $apiRequestsLimit;
    private array $features;
    private bool $active;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $id,
        string $name,
        ?string $description = null,
        float $priceMonthly = 0.00,
        float $priceYearly = 0.00,
        int $apiRequestsLimit = 1000,
        array $features = [],
        bool $active = true,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->priceMonthly = $priceMonthly;
        $this->priceYearly = $priceYearly;
        $this->apiRequestsLimit = $apiRequestsLimit;
        $this->features = $features;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPriceMonthly(): float
    {
        return $this->priceMonthly;
    }

    public function getPriceYearly(): float
    {
        return $this->priceYearly;
    }

    public function getApiRequestsLimit(): int
    {
        return $this->apiRequestsLimit;
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function isActive(): bool
    {
        return $this->active;
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
    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function setPriceMonthly(float $priceMonthly): void
    {
        $this->priceMonthly = max(0.0, $priceMonthly);
        $this->touch();
    }

    public function setPriceYearly(float $priceYearly): void
    {
        $this->priceYearly = max(0.0, $priceYearly);
        $this->touch();
    }

    public function setApiRequestsLimit(int $apiRequestsLimit): void
    {
        $this->apiRequestsLimit = max(0, $apiRequestsLimit);
        $this->touch();
    }

    public function setFeatures(array $features): void
    {
        $this->features = $features;
        $this->touch();
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->touch();
    }

    // Business logic methods
    public function isFree(): bool
    {
        return $this->priceMonthly <= 0 && $this->priceYearly <= 0;
    }

    public function hasFeature(string $feature): bool
    {
        return isset($this->features[$feature]) && $this->features[$feature] === true;
    }

    public function getFeature(string $feature, mixed $default = null): mixed
    {
        return $this->features[$feature] ?? $default;
    }

    public function addFeature(string $feature, mixed $value = true): void
    {
        $this->features[$feature] = $value;
        $this->touch();
    }

    public function removeFeature(string $feature): void
    {
        unset($this->features[$feature]);
        $this->touch();
    }

    public function getYearlySavings(): float
    {
        if ($this->priceMonthly <= 0) {
            return 0.0;
        }
        
        $yearlyFromMonthly = $this->priceMonthly * 12;
        return max(0.0, $yearlyFromMonthly - $this->priceYearly);
    }

    public function getYearlySavingsPercentage(): float
    {
        if ($this->priceMonthly <= 0) {
            return 0.0;
        }
        
        $yearlyFromMonthly = $this->priceMonthly * 12;
        if ($yearlyFromMonthly <= 0) {
            return 0.0;
        }
        
        return round(($this->getYearlySavings() / $yearlyFromMonthly) * 100, 2);
    }

    public function isPopular(): bool
    {
        return $this->id === 'professional' || $this->getFeature('popular', false);
    }

    public function isEnterprise(): bool
    {
        return $this->id === 'enterprise' || $this->getFeature('enterprise', false);
    }

    public function supportsAnalytics(): bool
    {
        return $this->hasFeature('analytics');
    }

    public function supportsCustomDomains(): bool
    {
        return $this->hasFeature('custom_domains');
    }

    public function supportsWhiteLabel(): bool
    {
        return $this->hasFeature('white_label');
    }

    public function getSupportLevel(): string
    {
        return $this->getFeature('support', 'community');
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

    // Utility methods
    private function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => $this->priceMonthly,
            'price_yearly' => $this->priceYearly,
            'api_requests_limit' => $this->apiRequestsLimit,
            'features' => $this->features,
            'active' => $this->active,
            'is_free' => $this->isFree(),
            'is_popular' => $this->isPopular(),
            'is_enterprise' => $this->isEnterprise(),
            'yearly_savings' => $this->getYearlySavings(),
            'yearly_savings_percentage' => $this->getYearlySavingsPercentage(),
            'support_level' => $this->getSupportLevel(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}