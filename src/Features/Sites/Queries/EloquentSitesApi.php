<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Queries;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\AdjustMerchantPrepaidBalance;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RecordSiteSyncStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;

final class EloquentSitesApi implements SitesApi
{
    public function __construct(
        private readonly SiteLookupQuery $lookup,
        private readonly RecordSiteSyncStatus $recordSyncStatus,
        private readonly AdjustMerchantPrepaidBalance $adjustPrepaidBalance,
    ) {}

    public function findById(string $siteId): ?SiteRecord
    {
        return $this->lookup->findById($siteId)?->toRecord();
    }

    public function findByIdUnscoped(string $siteId): ?SiteRecord
    {
        return $this->lookup->findByIdUnscoped($siteId)?->toRecord();
    }

    public function findByIdOrDomainUnscoped(string $identifier): ?SiteRecord
    {
        return $this->lookup->findByIdOrDomainUnscoped($identifier)?->toRecord();
    }

    public function isVerified(string $siteId): bool
    {
        return $this->lookup->findByIdUnscoped($siteId)?->isVerified() ?? false;
    }

    public function matchesCatalogToken(string $siteId, ?string $token): bool
    {
        return $this->lookup->findByIdUnscoped($siteId)?->matchesCatalogToken($token) ?? false;
    }

    public function recordSyncStatus(string $siteId, SiteSyncStatus $status): void
    {
        $this->recordSyncStatus->execute($siteId, $status);
    }

    public function adjustPrepaidBalance(string $siteId, int $deltaMinor): int
    {
        return $this->adjustPrepaidBalance->execute($siteId, $deltaMinor);
    }

    public function countVerifiedSitesUnscoped(): int
    {
        return AffiliateSite::query()->withoutOwnerScope()->where('status', SiteStatus::Verified)->count();
    }
}
