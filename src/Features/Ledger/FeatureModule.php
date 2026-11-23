<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger;

use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners\NotifyNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners\ReverseNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\EloquentLedgerApi;
use AIArmada\AffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(LedgerApi::class, EloquentLedgerApi::class);

        config([
            'affiliate-network.models.conversion_leg' => NetworkConversionLeg::class,
            'affiliate-network.models.merchant_statement' => MerchantStatement::class,
        ]);
    }

    public function boot(Application $app): void
    {
        $this->loadRoutes();

        if ($this->affiliatesLoaded()) {
            $reversalEventClass = 'AIArmada\\Affiliates\\Events\\AffiliateConversionReversed';

            if (class_exists($reversalEventClass)) {
                Event::listen(
                    $reversalEventClass,
                    ReverseNetworkConversion::class,
                );
            }
        }

        Event::listen(NetworkConversionRecorded::class, NotifyNetworkConversion::class);
    }

    private function loadRoutes(): void
    {
        $routes = __DIR__ . '/Routes/api.php';

        if (! is_file($routes)) {
            return;
        }

        Route::middleware((array) config('affiliate-network.postbacks.middleware', ['api', 'throttle:60,1']))
            ->prefix((string) config('affiliate-network.postbacks.prefix', 'api/affiliate-network'))
            ->group($routes);
    }

    private function affiliatesLoaded(): bool
    {
        return isset(app()->getLoadedProviders()['AIArmada\Affiliates\AffiliatesServiceProvider']);
    }
}
