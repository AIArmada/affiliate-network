<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services\Catalog;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;

/**
 * Site-aware catalog snapshot. Local ignores the site (shared DB);
 * remote reads catalog_url/token off the site (unowned installs).
 */
interface CatalogReaderInterface
{
    /**
     * @return array{version: string, program_id: string, currency: string|null, cookie_days: int|null, base: array<string, mixed>, subjects: array<int, array<string, mixed>>, creatives: array<int, array{id: string, type: string, name: string, description: string|null, asset_url: string|null, destination_url: string|null, width: int|null, height: int|null, tracking_code: string, metadata: array<string, mixed>|null}>, variable_extras: array<string, mixed>}
     */
    public function snapshot(AffiliateSite $site, string $programId): array;

    /** @return array<int, string> */
    public function programIds(AffiliateSite $site): array;
}
