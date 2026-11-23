<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Api;

use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;

interface SitesApi
{
    public function findById(string $siteId): ?SiteRecord;

    public function findByIdUnscoped(string $siteId): ?SiteRecord;

    public function findByIdOrDomainUnscoped(string $identifier): ?SiteRecord;

    public function isVerified(string $siteId): bool;

    public function matchesCatalogToken(string $siteId, ?string $token): bool;

    public function recordSyncStatus(string $siteId, SiteSyncStatus $status): void;

    public function adjustPrepaidBalance(string $siteId, int $deltaMinor): int;
}
