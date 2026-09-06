<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services\Catalog;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;

/**
 * Site-aware catalog snapshot. Local ignores the site (shared DB);
 * remote reads catalog_url/token off the site (unowned installs).
 *
 * @return array{version: string, program_id: string, currency: string|null, cookie_days: int|null, base: array<string, mixed>, subjects: array<int, array<string, mixed>>, variable_extras: array<string, mixed>}
 */
interface CatalogReaderInterface
{
    public function snapshot(AffiliateSite $site, string $programId): array;

    /** @return array<int, string> */
    public function programIds(AffiliateSite $site): array;
}
