<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;

final class DeleteSiteDependents
{
    public function execute(string $siteId): void
    {
        app(CatalogApi::class)->deleteOffersForSite($siteId);
    }
}
