<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork;

use AIArmada\AffiliateNetwork\Adapters\Affiliates\AffiliatesCatalogReader;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\AffiliatesIdentityReader;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\AffiliatesLedgerPoster;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\EnginePayoutFulfillment;
use AIArmada\AffiliateNetwork\Console\Commands\ArchiveExpiredOffersCommand;
use AIArmada\AffiliateNetwork\Console\Commands\ReconcileNetworkLedgerCommand;
use AIArmada\AffiliateNetwork\Console\Commands\SyncSiteOffersCommand;
use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Listeners\FinalizeNetworkAttribution;
use AIArmada\AffiliateNetwork\Listeners\IncrementNetworkLinkClicks;
use AIArmada\AffiliateNetwork\Listeners\NotifyApplicationApproved;
use AIArmada\AffiliateNetwork\Listeners\NotifyApplicationSubmitted;
use AIArmada\AffiliateNetwork\Listeners\NotifyNetworkConversion;
use AIArmada\AffiliateNetwork\Listeners\RecordProvisionalNetworkConversion;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderResolver;
use AIArmada\AffiliateNetwork\Services\CreatorBalances;
use AIArmada\AffiliateNetwork\Services\HostManualFulfillment;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;
use AIArmada\AffiliateNetwork\Strategies\DnsVerificationStrategy;
use AIArmada\AffiliateNetwork\Strategies\FileVerificationStrategy;
use AIArmada\AffiliateNetwork\Strategies\MetaTagVerificationStrategy;
use AIArmada\AffiliateNetwork\Support\OfferLinkGate;
use AIArmada\AffiliateNetwork\Support\SiteContentFetcher;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Events\LinkClicked;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
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
                SyncSiteOffersCommand::class,
                ReconcileNetworkLedgerCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SiteContentFetcher::class);
        $this->app->singleton(SiteVerificationService::class);
        $this->app->singleton(OfferManagementService::class);
        $this->app->singleton(OfferLinkService::class);
        $this->app->singleton(CatalogReaderResolver::class);
        $this->app->singleton(Services\Catalog\RemoteCatalogClient::class);
        $this->app->singleton(Services\OfferImportService::class);
        $this->app->singleton(NetworkBooks::class);
        $this->app->singleton(CreatorBalances::class);
        $this->app->singleton(NetworkLedgerReconciliationService::class);

        $this->app->bind(LinkGateInterface::class, OfferLinkGate::class);

        // Standalone defaults. When the engine is installed, boot-time
        // rebinding swaps every seam to its engine adapter — provider
        // order independent, since boot runs after all registration.
        $this->app->bind(AffiliateIdentityResolver::class, Support\UserKeyAffiliateIdentityResolver::class);
        $this->app->bind(Fulfillment::class, HostManualFulfillment::class);

        $this->registerVerificationStrategies();
    }

    public function packageBooted(): void
    {
        Event::listen(LinkClicked::class, IncrementNetworkLinkClicks::class);
        Event::listen(ApplicationSubmitted::class, NotifyApplicationSubmitted::class);
        Event::listen(ApplicationApproved::class, NotifyApplicationApproved::class);
        Event::listen(NetworkConversionRecorded::class, NotifyNetworkConversion::class);

        if ($this->engineInstalled()) {
            $this->bindEngineAdapters();
        }

        $this->registerAffiliateModel();
        $this->registerMorphMap();
        $this->bootCheckoutIntegration();
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'affiliate_offer' => Models\AffiliateOffer::class,
            'affiliate_offer_application' => Models\AffiliateOfferApplication::class,
            'affiliate_offer_category' => Models\AffiliateOfferCategory::class,
            'affiliate_offer_creative' => Models\AffiliateOfferCreative::class,
            'affiliate_offer_link' => Models\AffiliateOfferLink::class,
            'affiliate_site' => Models\AffiliateSite::class,
            'network_conversion_leg' => Models\NetworkConversionLeg::class,
        ]);
    }

    /**
     * Engine presence means an active engine provider, not merely
     * autoloadable engine classes (every monorepo package autoloads).
     */
    private function engineInstalled(): bool
    {
        $providers = $this->app->getLoadedProviders();

        return isset($providers['AIArmada\Affiliates\AffiliatesServiceProvider']);
    }

    private function bindEngineAdapters(): void
    {
        $this->app->bind(AffiliateIdentityResolver::class, AffiliatesIdentityReader::class);
        $this->app->bind(NetworkLedger::class, AffiliatesLedgerPoster::class);
        $this->app->bind(Fulfillment::class, EnginePayoutFulfillment::class);
        $this->app->bind(CatalogReaderResolver::LOCAL_READER_KEY, AffiliatesCatalogReader::class);

        config(['affiliate-network.models.affiliate' => 'AIArmada\Affiliates\Models\Affiliate']);
    }

    private function registerAffiliateModel(): void
    {
        if (config('affiliate-network.models.affiliate') !== null) {
            return;
        }

        $userModel = config('auth.providers.users.model');

        if (is_string($userModel) && class_exists($userModel)) {
            config(['affiliate-network.models.affiliate' => $userModel]);
        }
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
        $this->registerOrderListeners();
    }

    private function registerCookieMiddleware(): void
    {
        $middlewareGroup = config('affiliate-network.checkout.middleware_group', 'web');

        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup($middlewareGroup, TrackNetworkLinkCookie::class);
    }

    /**
     * Attribution listeners in decision order.
     *
     * Provisional (network touch + leg), then the engine listener when
     * installed (touch + winner decision), then the finalizer (confirm
     * or supersede + fulfill). The engine self-registers only when this
     * package is absent, so shared installs record exactly once.
     */
    private function registerOrderListeners(): void
    {
        if (! config('affiliate-network.checkout.listen_for_orders', true)) {
            return;
        }

        $eventClass = 'AIArmada\\Orders\\Events\\CommissionAttributionRequired';

        if (! class_exists($eventClass)) {
            return;
        }

        Event::listen($eventClass, RecordProvisionalNetworkConversion::class);

        if ($this->engineInstalled()) {
            Event::listen($eventClass, 'AIArmada\Affiliates\Listeners\RecordCommissionForOrder');
        }

        Event::listen($eventClass, FinalizeNetworkAttribution::class);
    }
}
