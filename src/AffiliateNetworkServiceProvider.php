<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\CatalogReader;
use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Contracts\DnsResolver;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Contracts\NotificationDispatcher;
use AIArmada\AffiliateNetwork\Contracts\SiteContentClient;
use AIArmada\AffiliateNetwork\Drivers\Affiliates\AffiliatesCatalogDriver;
use AIArmada\AffiliateNetwork\Drivers\Affiliates\AffiliatesIdentityDriver;
use AIArmada\AffiliateNetwork\Drivers\Affiliates\AffiliatesLedgerDriver;
use AIArmada\AffiliateNetwork\Drivers\Affiliates\EnginePayoutFulfillmentDriver;
use AIArmada\AffiliateNetwork\Drivers\CommunicationsNotificationDriver;
use AIArmada\AffiliateNetwork\Drivers\HostManualFulfillmentDriver;
use AIArmada\AffiliateNetwork\Drivers\HttpCreatorPostbackDriver;
use AIArmada\AffiliateNetwork\Drivers\HttpSiteContentDriver;
use AIArmada\AffiliateNetwork\Drivers\NativeDnsDriver;
use AIArmada\AffiliateNetwork\Drivers\RemoteCatalogHttpDriver;
use AIArmada\AffiliateNetwork\Drivers\UserKeyIdentityDriver;
use AIArmada\AffiliateNetwork\Features\Attribution\EntryPoints\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Features\Attribution\Extensions\OfferLinkGate;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\EntryPoints\Console\ArchiveExpiredOffersCommand;
use AIArmada\AffiliateNetwork\Features\Catalog\EntryPoints\Console\SyncSiteOffersCommand;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Console\GenerateMerchantStatementsCommand;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Console\LockMaturedLegsCommand;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Console\ReconcileNetworkLedgerCommand;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners\FinalizeNetworkAttribution;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners\RecordProvisionalNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Platform\ExtensionRegistry;
use AIArmada\AffiliateNetwork\Platform\FeatureCatalog;
use AIArmada\AffiliateNetwork\Platform\FeatureModule;
use AIArmada\Links\Contracts\LinkGateInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AffiliateNetworkServiceProvider extends PackageServiceProvider
{
    public const string LOCAL_READER_KEY = 'affiliate-network.catalog.local-reader';

    /** @var array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}|null */
    private ?array $catalog = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('affiliate-network')
            ->hasConfigFile('affiliate-network')
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                ArchiveExpiredOffersCommand::class,
                SyncSiteOffersCommand::class,
                LockMaturedLegsCommand::class,
                ReconcileNetworkLedgerCommand::class,
                GenerateMerchantStatementsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $catalog = $this->catalog();

        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry(
            $app,
            $catalog['extensions'],
        ));

        foreach (array_keys($catalog['features']) as $name) {
            $instance = $this->app->make($this->moduleClass($name));

            if ($instance instanceof FeatureModule) {
                $instance->register($this->app);
            }
        }

        $this->app->bind(AffiliateIdentityResolver::class, UserKeyIdentityDriver::class);
        $this->app->bind(Fulfillment::class, HostManualFulfillmentDriver::class);
        $this->app->bind(NotificationDispatcher::class, CommunicationsNotificationDriver::class);
        $this->app->bind(SiteContentClient::class, HttpSiteContentDriver::class);
        $this->app->bind(DnsResolver::class, NativeDnsDriver::class);
        $this->app->bind(CatalogReader::class, RemoteCatalogHttpDriver::class);
        $this->app->bind(CreatorPostbackTransport::class, HttpCreatorPostbackDriver::class);

        $this->app->tag([OfferLinkGate::class], LinkGateInterface::class);
    }

    public function packageBooted(): void
    {
        if ($this->engineInstalled()) {
            $this->bindEngineAdapters();
        }

        $this->registerAffiliateModel();

        foreach (array_keys($this->catalog()['features']) as $name) {
            $instance = $this->app->make($this->moduleClass($name));

            if ($instance instanceof FeatureModule) {
                $instance->boot($this->app);
            }
        }

        $this->registerMorphMap();
        $this->bootCheckoutIntegration();
    }

    private function moduleClass(string $feature): string
    {
        return __NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule';
    }

    /**
     * @return array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}
     */
    private function catalog(): array
    {
        return $this->catalog ??= FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
    }

    private function engineInstalled(): bool
    {
        return isset($this->app->getLoadedProviders()['AIArmada\Affiliates\AffiliatesServiceProvider']);
    }

    private function bindEngineAdapters(): void
    {
        $this->app->bind(AffiliateIdentityResolver::class, AffiliatesIdentityDriver::class);
        $this->app->bind(NetworkLedger::class, AffiliatesLedgerDriver::class);
        $this->app->bind(Fulfillment::class, EnginePayoutFulfillmentDriver::class);
        $this->app->bind(self::LOCAL_READER_KEY, AffiliatesCatalogDriver::class);

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

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'affiliate_site' => AffiliateSite::class,
            'affiliate_offer_category' => AffiliateOfferCategory::class,
            'affiliate_offer' => AffiliateOffer::class,
            'affiliate_offer_creative' => AffiliateOfferCreative::class,
            'affiliate_offer_application' => AffiliateOfferApplication::class,
            'affiliate_offer_invitation' => AffiliateOfferInvitation::class,
            'affiliate_offer_deal' => AffiliateOfferDeal::class,
            'affiliate_offer_link' => AffiliateOfferLink::class,
            'affiliate_offer_coupon' => AffiliateOfferCoupon::class,
            'network_conversion_leg' => NetworkConversionLeg::class,
            'merchant_statement' => MerchantStatement::class,
            'creator_postback_endpoint' => CreatorPostbackEndpoint::class,
            'creator_postback_delivery' => CreatorPostbackDelivery::class,
        ]);
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
        $middlewareGroup = (string) config('affiliate-network.checkout.middleware_group', 'web');

        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup($middlewareGroup, TrackNetworkLinkCookie::class);
    }

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

        if ($this->engineInstalled()
            && config('affiliates.features.commission_tracking.enabled', true)
            && class_exists('AIArmada\\Cart\\CartManager')) {
            Event::listen($eventClass, 'AIArmada\Affiliates\Listeners\RecordCommissionForOrder');
        }

        Event::listen($eventClass, FinalizeNetworkAttribution::class);
    }
}
