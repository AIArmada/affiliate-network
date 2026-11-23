<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites;

use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Queries\EloquentSitesApi;
use AIArmada\AffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SitesApi::class, EloquentSitesApi::class);

        config(['affiliate-network.models.site' => AffiliateSite::class]);
    }

    public function boot(Application $app): void {}
}
