<?php

declare(strict_types=1);

namespace LinkShortener\Models;

use DateTime;
use JsonSerializable;

class Partner implements JsonSerializable
{
    private ?string $id;
    private string $companyName;
    private string $email;
    private string $passwordHash;
    private ?string $phone;
    private bool $emailVerified;
    private bool $phoneVerified;
    private string $apiKey;
    private string $apiSecret;
    private float $revenueShare;
    private string $planId;
    private string $status;
    private bool $mfaEnabled;
    private ?string $mfaSecret;
    private ?DateTime $lastLogin;
    private int $loginAttempts;
    private ?DateTime $lockedUntil;
    private ?string $stripeCustomerId;
    private DateTime $createdAt;
    private DateTime $updatedAt;
    
    // Related objects
    private ?PartnerProfile $profile = null;
    private ?SubscriptionPlan $subscriptionPlan = null;
    private array $sessions = [];
    private array $apiTokens = [];

    public function __construct(
        string $companyName,
        string $email,
        string $passwordHash,
        string $apiKey,
        string $apiSecret,
        ?string $id = null,
        ?string $phone = null,
        bool $emailVerified = false,
        bool $phoneVerified = false,
        float $revenueShare = 0.50,
        string $planId = 'free',
        string $status = 'pending',
        bool $mfaEnabled = false,
        ?string $mfaSecret = null,
        ?DateTime $lastLogin = null,
        int $loginAttempts = 0,
        ?DateTime $lockedUntil = null,
        ?string $stripeCustomerId = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->companyName = $companyName;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->phone = $phone;
        $this->emailVerified = $emailVerified;
        $this->phoneVerified = $phoneVerified;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->revenueShare = $revenueShare;
        $this->planId = $planId;
        $this->status = $status;
        $this->mfaEnabled = $mfaEnabled;
        $this->mfaSecret = $mfaSecret;
        $this->lastLogin = $lastLogin;
        $this->loginAttempts = $loginAttempts;
        $this->lockedUntil = $lockedUntil;
        $this->stripeCustomerId = $stripeCustomerId;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    // Getters
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiSecret(): string
    {
        return $this->apiSecret;
    }

    public function getRevenueShare(): float
    {
        return $this->revenueShare;
    }

    public function getPlanId(): string
    {
        return $this->planId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isMfaEnabled(): bool
    {
        return $this->mfaEnabled;
    }

    public function getMfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    public function getLastLogin(): ?DateTime
    {
        return $this->lastLogin;
    }

    public function getLoginAttempts(): int
    {
        return $this->loginAttempts;
    }

    public function getLockedUntil(): ?DateTime
    {
        return $this->lockedUntil;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
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
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setCompanyName(string $companyName): void
    {
        $this->companyName = $companyName;
        $this->touch();
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
        $this->emailVerified = false; // Reset verification when email changes
        $this->touch();
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->touch();
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
        $this->phoneVerified = false; // Reset verification when phone changes
        $this->touch();
    }

    public function setEmailVerified(bool $emailVerified): void
    {
        $this->emailVerified = $emailVerified;
        $this->touch();
    }

    public function setPhoneVerified(bool $phoneVerified): void
    {
        $this->phoneVerified = $phoneVerified;
        $this->touch();
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
        $this->touch();
    }

    public function setApiSecret(string $apiSecret): void
    {
        $this->apiSecret = $apiSecret;
        $this->touch();
    }

    public function setRevenueShare(float $revenueShare): void
    {
        $this->revenueShare = max(0.0, min(1.0, $revenueShare)); // Clamp between 0 and 1
        $this->touch();
    }

    public function setPlanId(string $planId): void
    {
        $this->planId = $planId;
        $this->touch();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function setMfaEnabled(bool $mfaEnabled): void
    {
        $this->mfaEnabled = $mfaEnabled;
        $this->touch();
    }

    public function setMfaSecret(?string $mfaSecret): void
    {
        $this->mfaSecret = $mfaSecret;
        $this->touch();
    }

    public function setLastLogin(?DateTime $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
        $this->touch();
    }

    public function setLoginAttempts(int $loginAttempts): void
    {
        $this->loginAttempts = max(0, $loginAttempts);
        $this->touch();
    }

    public function setLockedUntil(?DateTime $lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
        $this->touch();
    }

    public function setStripeCustomerId(?string $stripeCustomerId): void
    {
        $this->stripeCustomerId = $stripeCustomerId;
        $this->touch();
    }

    // Related objects
    public function getProfile(): ?PartnerProfile
    {
        return $this->profile;
    }

    public function setProfile(?PartnerProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function getSubscriptionPlan(): ?SubscriptionPlan
    {
        return $this->subscriptionPlan;
    }

    public function setSubscriptionPlan(?SubscriptionPlan $subscriptionPlan): void
    {
        $this->subscriptionPlan = $subscriptionPlan;
    }

    public function getSessions(): array
    {
        return $this->sessions;
    }

    public function setSessions(array $sessions): void
    {
        $this->sessions = $sessions;
    }

    public function getApiTokens(): array
    {
        return $this->apiTokens;
    }

    public function setApiTokens(array $apiTokens): void
    {
        $this->apiTokens = $apiTokens;
    }

    // Business logic methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > new DateTime();
    }

    public function canLogin(): bool
    {
        return $this->isActive() && !$this->isLocked();
    }

    public function incrementLoginAttempts(): void
    {
        $this->loginAttempts++;
        
        // Lock account after 5 failed attempts for 30 minutes
        if ($this->loginAttempts >= 5) {
            $this->lockedUntil = new DateTime('+30 minutes');
        }
        
        $this->touch();
    }

    public function resetLoginAttempts(): void
    {
        $this->loginAttempts = 0;
        $this->lockedUntil = null;
        $this->lastLogin = new DateTime();
        $this->touch();
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->touch();
    }

    public function suspend(): void
    {
        $this->status = 'suspended';
        $this->touch();
    }

    public function close(): void
    {
        $this->status = 'closed';
        $this->touch();
    }

    public function verifyEmail(): void
    {
        $this->emailVerified = true;
        $this->touch();
    }

    public function verifyPhone(): void
    {
        $this->phoneVerified = true;
        $this->touch();
    }

    public function enableMfa(string $secret): void
    {
        $this->mfaEnabled = true;
        $this->mfaSecret = $secret;
        $this->touch();
    }

    public function disableMfa(): void
    {
        $this->mfaEnabled = false;
        $this->mfaSecret = null;
        $this->touch();
    }

    public function rotateApiKey(string $newApiKey, string $newApiSecret): void
    {
        $this->apiKey = $newApiKey;
        $this->apiSecret = $newApiSecret;
        $this->touch();
    }

    public function upgradePlan(string $planId): void
    {
        $this->planId = $planId;
        $this->touch();
    }

    public function calculateEarnings(float $grossRevenue): float
    {
        return $grossRevenue * $this->revenueShare;
    }

    // Validation methods
    public function isValidForApiAccess(): bool
    {
        return $this->isActive() && 
               $this->emailVerified && 
               !empty($this->apiKey) && 
               !empty($this->apiSecret);
    }

    public function isValidForRevenuePayout(): bool
    {
        return $this->isActive() && 
               $this->emailVerified && 
               $this->profile !== null && 
               $this->profile->getKycStatus() === 'verified';
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
            'company_name' => $this->companyName,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->emailVerified,
            'phone_verified' => $this->phoneVerified,
            'revenue_share' => $this->revenueShare,
            'plan_id' => $this->planId,
            'status' => $this->status,
            'mfa_enabled' => $this->mfaEnabled,
            'last_login' => $this->lastLogin?->format('Y-m-d H:i:s'),
            'login_attempts' => $this->loginAttempts,
            'locked_until' => $this->lockedUntil?->format('Y-m-d H:i:s'),
            'stripe_customer_id' => $this->stripeCustomerId,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->companyName,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->emailVerified,
            'phone_verified' => $this->phoneVerified,
            'plan_id' => $this->planId,
            'status' => $this->status,
            'mfa_enabled' => $this->mfaEnabled,
            'last_login' => $this->lastLogin?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toPublicArray();
    }
}