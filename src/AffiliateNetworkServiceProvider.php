<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork;

use AIArmada\AffiliateNetwork\Console\Commands\ArchiveExpiredOffersCommand;
use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Listeners\RecordNetworkConversionForOrder;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;
use AIArmada\AffiliateNetwork\Strategies\DnsVerificationStrategy;
use AIArmada\AffiliateNetwork\Strategies\FileVerificationStrategy;
use AIArmada\AffiliateNetwork\Strategies\MetaTagVerificationStrategy;
use AIArmada\AffiliateNetwork\Support\SiteContentFetcher;
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
            ->runsMigrations()
            ->discoversMigrations()
            ->hasRoutes(['api'])
            ->hasCommands([
                ArchiveExpiredOffersCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SiteContentFetcher::class);
        $this->app->singleton(SiteVerificationService::class);
        $this->app->singleton(OfferManagementService::class);
        $this->app->singleton(OfferLinkService::class);

        $this->registerVerificationStrategies();
    }

    public function packageBooted(): void
    {
        $this->bootCheckoutIntegration();
    }

    private function registerVerificationStrategies(): void
    {
        $this->app->tag([
            DnsVerificationStrategy::class,
            MetaTagVerificationStrategy::class,
            FileVerificationStrategy::class,
        ], 'affiliate-network.site_verification_strategy');
    }

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
