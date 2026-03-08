<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork;

use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Listeners\RecordNetworkConversionForOrder;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AffiliateNetworkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('affiliate-network')
            ->hasConfigFile('affiliate-network')
            ->discoversMigrations()
            ->hasRoutes(['api']);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SiteVerificationService::class);
        $this->app->singleton(OfferManagementService::class);
        $this->app->singleton(OfferLinkService::class);
    }

    public function packageBooted(): void
    {
        $this->bootCheckoutIntegration();
    }

    /**
     * Boot checkout integration (Scenario B support).
     *
     * Registers cookie tracking middleware and order conversion listener
     * when the site uses the checkout package with network affiliate links.
     */
    private function bootCheckoutIntegration(): void
    {
        if (! config('affiliate-network.checkout.enabled', false)) {
            return;
        }

        $this->registerCookieMiddleware();
        $this->registerOrderListener();
    }

    private function registerCookieMiddleware(): void
    {
        $middlewareGroup = config('affiliate-network.checkout.middleware_group', 'web');

        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup($middlewareGroup, TrackNetworkLinkCookie::class);
    }

    private function registerOrderListener(): void
    {
        if (! config('affiliate-network.checkout.listen_for_orders', true)) {
            return;
        }

        $eventClass = 'AIArmada\\Orders\\Events\\CommissionAttributionRequired';

        if (! class_exists($eventClass)) {
            return;
        }

        Event::listen($eventClass, RecordNetworkConversionForOrder::class);
    }
}
