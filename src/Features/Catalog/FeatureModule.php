<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog;

use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\EloquentCatalogApi;
use AIArmada\AffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CatalogApi::class, EloquentCatalogApi::class);

        config([
            'affiliate-network.models.offer' => AffiliateOffer::class,
            'affiliate-network.models.offer_category' => AffiliateOfferCategory::class,
            'affiliate-network.models.offer_creative' => AffiliateOfferCreative::class,
        ]);
    }

    public function boot(Application $app): void {}
}
