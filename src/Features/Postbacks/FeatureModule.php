<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks;

use AIArmada\AffiliateNetwork\Events\ConversionAdjusted;
use AIArmada\AffiliateNetwork\Events\ConversionLocked;
use AIArmada\AffiliateNetwork\Events\ConversionReversed;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Postbacks\Api\PostbacksApi;
use AIArmada\AffiliateNetwork\Features\Postbacks\EntryPoints\Listeners\DispatchCreatorPostbacks;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Features\Postbacks\Queries\EloquentPostbacksApi;
use AIArmada\AffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PostbacksApi::class, EloquentPostbacksApi::class);

        config([
            'affiliate-network.models.creator_postback' => CreatorPostbackEndpoint::class,
            'affiliate-network.models.postback_delivery' => CreatorPostbackDelivery::class,
        ]);
    }

    public function boot(Application $app): void
    {
        Event::listen(NetworkConversionRecorded::class, DispatchCreatorPostbacks::class);
        Event::listen(ConversionLocked::class, DispatchCreatorPostbacks::class);
        Event::listen(ConversionReversed::class, DispatchCreatorPostbacks::class);
        Event::listen(ConversionAdjusted::class, DispatchCreatorPostbacks::class);
    }
}
