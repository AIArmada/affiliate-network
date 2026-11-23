<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution;

use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\EntryPoints\Listeners\IncrementNetworkLinkClicks;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Queries\EloquentAttributionApi;
use AIArmada\AffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Links\Events\LinkClicked;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(AttributionApi::class, EloquentAttributionApi::class);

        config([
            'affiliate-network.models.offer_link' => AffiliateOfferLink::class,
            'affiliate-network.models.offer_coupon' => AffiliateOfferCoupon::class,
        ]);
    }

    public function boot(Application $app): void
    {
        Event::listen(LinkClicked::class, IncrementNetworkLinkClicks::class);
    }
}
