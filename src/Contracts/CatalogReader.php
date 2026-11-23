<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\SiteCatalogEndpoint;

interface CatalogReader
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(SiteCatalogEndpoint $site, string $programId): array;

    /**
     * @return array<int, string>
     */
    public function programIds(SiteCatalogEndpoint $site): array;
}
