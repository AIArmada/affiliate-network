<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Api;

use AIArmada\AffiliateNetwork\Data\SiteCatalogEndpoint;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;
use Carbon\CarbonImmutable;

final readonly class SiteRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public string $domain,
        public SiteStatus $status,
        public ?CarbonImmutable $verifiedAt,
        public ?string $verificationMethod,
        public ?string $verificationToken,
        public ?string $catalogUrl,
        public ?string $catalogTokenEncrypted,
        public SiteSyncStatus $syncStatus,
        public int $defaultLockingDays,
        public ?int $prepaidBalanceMinor,
        public ?string $budgetCurrency,
        public ?int $monthlySpendCapMinor,
        public bool $requireSettledForPayout,
        public ?string $ownerType,
        public string | int | null $ownerId,
    ) {}

    public function isVerified(): bool
    {
        return $this->status === SiteStatus::Verified && $this->verifiedAt !== null;
    }

    public function toCatalogEndpoint(): SiteCatalogEndpoint
    {
        return new SiteCatalogEndpoint(
            siteId: $this->id,
            domain: $this->domain,
            catalogUrl: $this->catalogUrl,
            catalogTokenEncrypted: $this->catalogTokenEncrypted,
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
        );
    }
}
