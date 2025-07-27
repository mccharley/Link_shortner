<?php

declare(strict_types=1);

namespace LinkShortener\Models;

use DateTime;
use JsonSerializable;

class PartnerProfile implements JsonSerializable
{
    private string $partnerId;
    private ?string $firstName;
    private ?string $lastName;
    private ?string $companyWebsite;
    private ?string $companyDescription;
    private ?array $billingAddress;
    private ?string $taxId;
    private string $businessType;
    private string $timezone;
    private array $notificationPreferences;
    private string $kycStatus;
    private ?array $kycDocuments;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $partnerId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $companyWebsite = null,
        ?string $companyDescription = null,
        ?array $billingAddress = null,
        ?string $taxId = null,
        string $businessType = 'company',
        string $timezone = 'UTC',
        array $notificationPreferences = [],
        string $kycStatus = 'pending',
        ?array $kycDocuments = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->partnerId = $partnerId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->companyWebsite = $companyWebsite;
        $this->companyDescription = $companyDescription;
        $this->billingAddress = $billingAddress;
        $this->taxId = $taxId;
        $this->businessType = $businessType;
        $this->timezone = $timezone;
        $this->notificationPreferences = $notificationPreferences;
        $this->kycStatus = $kycStatus;
        $this->kycDocuments = $kycDocuments;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    // Getters
    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): ?string
    {
        if ($this->firstName && $this->lastName) {
            return trim($this->firstName . ' ' . $this->lastName);
        }
        return $this->firstName ?? $this->lastName;
    }

    public function getCompanyWebsite(): ?string
    {
        return $this->companyWebsite;
    }

    public function getCompanyDescription(): ?string
    {
        return $this->companyDescription;
    }

    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function getBusinessType(): string
    {
        return $this->businessType;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getNotificationPreferences(): array
    {
        return $this->notificationPreferences;
    }

    public function getKycStatus(): string
    {
        return $this->kycStatus;
    }

    public function getKycDocuments(): ?array
    {
        return $this->kycDocuments;
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
    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
        $this->touch();
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
        $this->touch();
    }

    public function setCompanyWebsite(?string $companyWebsite): void
    {
        $this->companyWebsite = $companyWebsite;
        $this->touch();
    }

    public function setCompanyDescription(?string $companyDescription): void
    {
        $this->companyDescription = $companyDescription;
        $this->touch();
    }

    public function setBillingAddress(?array $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
        $this->touch();
    }

    public function setTaxId(?string $taxId): void
    {
        $this->taxId = $taxId;
        $this->touch();
    }

    public function setBusinessType(string $businessType): void
    {
        $this->businessType = $businessType;
        $this->touch();
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
        $this->touch();
    }

    public function setNotificationPreferences(array $notificationPreferences): void
    {
        $this->notificationPreferences = $notificationPreferences;
        $this->touch();
    }

    public function setKycStatus(string $kycStatus): void
    {
        $this->kycStatus = $kycStatus;
        $this->touch();
    }

    public function setKycDocuments(?array $kycDocuments): void
    {
        $this->kycDocuments = $kycDocuments;
        $this->touch();
    }

    // Business logic methods
    public function isKycPending(): bool
    {
        return $this->kycStatus === 'pending';
    }

    public function isKycVerified(): bool
    {
        return $this->kycStatus === 'verified';
    }

    public function isKycRejected(): bool
    {
        return $this->kycStatus === 'rejected';
    }

    public function verifyKyc(): void
    {
        $this->kycStatus = 'verified';
        $this->touch();
    }

    public function rejectKyc(): void
    {
        $this->kycStatus = 'rejected';
        $this->touch();
    }

    public function resetKyc(): void
    {
        $this->kycStatus = 'pending';
        $this->kycDocuments = null;
        $this->touch();
    }

    public function addKycDocument(string $type, string $filename, string $path): void
    {
        if ($this->kycDocuments === null) {
            $this->kycDocuments = [];
        }

        $this->kycDocuments[] = [
            'type' => $type,
            'filename' => $filename,
            'path' => $path,
            'uploaded_at' => (new DateTime())->format('Y-m-d H:i:s')
        ];

        $this->touch();
    }

    public function updateNotificationPreference(string $type, bool $enabled): void
    {
        $this->notificationPreferences[$type] = $enabled;
        $this->touch();
    }

    public function getNotificationPreference(string $type, bool $default = true): bool
    {
        return $this->notificationPreferences[$type] ?? $default;
    }

    public function isComplete(): bool
    {
        return !empty($this->firstName) &&
               !empty($this->lastName) &&
               !empty($this->billingAddress) &&
               $this->isKycVerified();
    }

    public function getCompletionPercentage(): int
    {
        $fields = [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'companyWebsite' => $this->companyWebsite,
            'companyDescription' => $this->companyDescription,
            'billingAddress' => $this->billingAddress,
            'taxId' => $this->taxId,
        ];

        $completed = 0;
        $total = count($fields);

        foreach ($fields as $field => $value) {
            if (!empty($value)) {
                $completed++;
            }
        }

        // Add KYC verification as a significant factor
        if ($this->isKycVerified()) {
            $completed += 2; // KYC counts as 2 fields
            $total += 2;
        } else {
            $total += 2;
        }

        return (int) round(($completed / $total) * 100);
    }

    // Utility methods
    private function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function toArray(): array
    {
        return [
            'partner_id' => $this->partnerId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'company_website' => $this->companyWebsite,
            'company_description' => $this->companyDescription,
            'billing_address' => $this->billingAddress,
            'tax_id' => $this->taxId,
            'business_type' => $this->businessType,
            'timezone' => $this->timezone,
            'notification_preferences' => $this->notificationPreferences,
            'kyc_status' => $this->kycStatus,
            'kyc_documents' => $this->kycDocuments,
            'completion_percentage' => $this->getCompletionPercentage(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'partner_id' => $this->partnerId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'company_website' => $this->companyWebsite,
            'company_description' => $this->companyDescription,
            'business_type' => $this->businessType,
            'timezone' => $this->timezone,
            'kyc_status' => $this->kycStatus,
            'completion_percentage' => $this->getCompletionPercentage(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toPublicArray();
    }
}