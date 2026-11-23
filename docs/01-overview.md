---
title: Overview
---

# Affiliate Network Package

## Purpose

`aiarmada/affiliate-network` is a standalone multi-merchant affiliate
marketplace: merchant sites, offers, creator enrollment, tracking links, an
append-only conversion ledger, merchant settlement statements, and outbound
creator postbacks. It never requires `aiarmada/affiliates`; when that package
is installed it binds local adapters for identity, the merchant ledger,
fulfillment, and catalog mirroring through the network seams.

> **warning**
> Breaking change: the network seam. Public APIs take affiliate IDs (`string`)
> instead of `Affiliate` models (`ApplyToOffer`, `CreateOfferLink`,
> `isApprovedForOffer`, and friends), and factories use `forAffiliateId()`.
> Every offer — mirrored or hand-written — enrolls through the network
> application flow, which never touches merchant program memberships.
> Migration: pass `(string) $affiliate->getKey()` at call sites and read
> enrollment state through network rows. There is no data migration —
> `affiliate_id` columns are unchanged.

> **warning**
> The package was reorganized from a flat `src/{Actions,Models,Services,...}`
> layout into a Modular Dune architecture. Every legacy namespace
> (`AIArmada\AffiliateNetwork\{Actions,Adapters,Console,Enums,Http,Listeners,Models,Services,Strategies,Support}\*`)
> is gone. Import from `Features\<Feature>\{Actions,Models,Api,Queries,Extensions,EntryPoints}`
> or the package-wide `Contracts`, `Data`, `Drivers`, `Events`, `Exceptions`,
> and `Notifications` namespaces instead.

## What this package owns

- Merchant sites, domain verification, catalog tokens, and settlement settings
- Offers, categories, creatives, invitations, custom deals, coupons, and tracking links
- Applications and enrollment state
- The append-only network conversion ledger (legs, caps, adjustments, reversals, locking)
- Merchant statements and receivables
- Outbound creator S2S postback delivery
- Marketplace discovery and per-offer metrics

## What this package does not own

- Core merchant attribution, commissions, payouts, or fraud models — those stay in `aiarmada/affiliates`
- Public redirect routing — served by `aiarmada/links` (`GET /go/{slug}`)
- Filament marketplace/admin surfaces — owned by `aiarmada/filament-affiliate-network`
- Checkout, cart, or order persistence beyond its integration hooks

## Related packages

- [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — optional: identity, merchant ledger, fulfillment, catalog snapshots
- [`aiarmada/filament-affiliate-network`](../../filament-affiliate-network/docs/01-overview.md) — Filament admin adapter
- [`aiarmada/links`](../../links/docs/01-overview.md) — tracked public redirects
- [`aiarmada/checkout`](../../checkout/docs/01-overview.md) — optional conversion recording bridge
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping, money helpers, HTTP guards

## Architecture (Modular Laravel Dune)

```
affiliate-network/
├── config/affiliate-network.php
├── database/{factories,migrations}/
├── resources/boost/{guidelines,skills}/     # downstream Laravel Boost assets
├── tools/{package-check.php,package-phpstan.neon,architecture.json,Architecture/}
└── src/
    ├── AffiliateNetworkServiceProvider.php  # loads feature catalog + extensions
    ├── Platform/                            # feature catalog, extension registry, owner scoping
    │   ├── FeatureCatalog.php
    │   ├── ExtensionRegistry.php
    │   ├── FeatureModule.php
    │   ├── ScopesByBelongsToOwner.php
    │   ├── DomainHostValidator.php
    │   └── ...
    ├── Contracts/                           # pure driver/seam interfaces
    ├── Data/                                # 11 enums + immutable DTOs
    ├── Drivers/                             # affiliates adapters, HTTP, DNS
    ├── Events/  Exceptions/  Notifications/
    └── Features/
        ├── Sites/        # site lifecycle, verification, tokens, prepaid balance
        ├── Catalog/      # offers, categories, creatives, catalog sync, discovery
        ├── Enrollment/   # applications, invitations, deals
        ├── Attribution/  # links, coupons, click counters
        ├── Ledger/       # conversion legs, caps, locking, statements, HTTP + listeners
        └── Postbacks/    # creator endpoint config + delivery
```

Each feature owns a `feature.json` manifest declaring the tables it writes
(`table:…`) and the exact writer classes allowed to mutate them. Sibling
features may only import another feature's `Api/` namespace.
`tools/package-check.php` and `tools/package-phpstan.neon` enforce those
boundaries mechanically.

### The six features

| Feature | Owns | Key surfaces |
|---|---|---|
| **Sites** | `affiliate_network_sites` | `CreateSite`, `RegisterSite`, `VerifySiteByStrategy`, `IssueCatalogToken`, `AdjustMerchantPrepaidBalance`, `SitesApi` |
| **Catalog** | `affiliate_network_{offers,offer_categories,offer_creatives}` | `CreateOffer`, `UpdateOffer`, `PublishOffer`, `SyncSiteOffers`, `OfferDiscoveryQuery`, `OfferMetricsQuery`, `CatalogApi` |
| **Enrollment** | `affiliate_network_{offer_applications,offer_invitations,offer_deals}` | `ApplyToOffer`, `ApproveApplication`, `InviteAffiliateToOffer`, `UpsertOfferDeal`, `EnrollmentApi` |
| **Attribution** | `affiliate_network_{offer_links,offer_coupons}` | `CreateOfferLink`, `CreateCreativeLink`, `AssignOfferCoupon`, `AttributionApi` |
| **Ledger** | `affiliate_network_{conversion_legs,merchant_statements}` | `RecordNetworkConversion`, `PostConversionLeg`, `LockMaturedLegs`, `GenerateMerchantStatement`, `CreatorBalancesQuery`, `LedgerApi` |
| **Postbacks** | `affiliate_network_{creator_postbacks,postback_deliveries}` | `ConfigureCreatorPostback`, `EnqueueCreatorPostbacks`, `DeliverCreatorPostback`, `PostbacksApi` |

### The thirteen models

`Features\Sites\Models\AffiliateSite` ·
`Features\Catalog\Models\{AffiliateOffer, AffiliateOfferCategory, AffiliateOfferCreative}` ·
`Features\Enrollment\Models\{AffiliateOfferApplication, AffiliateOfferInvitation, AffiliateOfferDeal}` ·
`Features\Attribution\Models\{AffiliateOfferLink, AffiliateOfferCoupon}` ·
`Features\Ledger\Models\{NetworkConversionLeg, MerchantStatement}` ·
`Features\Postbacks\Models\{CreatorPostbackEndpoint, CreatorPostbackDelivery}`

## The nine marketplace capabilities

1. **Validation locking window** — `Offer::locking_days`, `Site::default_locking_days`, or `locking.default_days` turn a posted conversion into a pending leg with a `locks_at` deadline; `LockMaturedLegs` matures it.
2. **Merchant statements & prepaid/spend caps** — period statements (`GenerateMerchantStatement` → `IssueMerchantStatement` → `MarkMerchantStatementPaid`/`VoidMerchantStatement`) plus `Site::prepaid_balance_minor` and `monthly_spend_cap_minor`.
3. **Private/unlisted invitations & per-affiliate deals** — `InviteAffiliateToOffer` issues tokens; `UpsertOfferDeal` overrides rates/fees per creator.
4. **Linkless promo coupons** — `AssignOfferCoupon` produces site-unique codes resolved by `AttributionApi::resolveCouponForSite` and attributed through `coupon_code`.
5. **Outbound creator S2S postbacks & sub IDs** — `ConfigureCreatorPostback` + `EnqueueCreatorPostbacks` deliver signed events with `sub1`/`sub2`/`sub3`.
6. **Offer caps & smart fallback redirects** — `CheckOfferCaps` enforces daily/monthly/total/per-affiliate/payout caps; the link gate falls back to `offer.fallback_url`, `offer.fallback_offer_id`, or `links.default_fallback_url`.
7. **Partial refund adjustments & multi-event CPA** — `AdjustConversionLeg` posts proportional companion legs; `event_rates` prices `signup`/`lead`/`purchase` separately.
8. **Marketplace discovery & metrics** — `OfferDiscoveryQuery` filters/sorts public offers; `OfferMetricsQuery` returns `OfferPerformanceMetrics`.
9. **Enforced restrictions & active promotions** — `restrictions` (country/channel/device/order) and `active_promotions` evaluated at posting and enforced on redirect.

## Database schema

All tables share the configurable `database.table_prefix` (`affiliate_network_`).

| Table | Owner | Purpose |
|---|---|---|
| `affiliate_network_sites` | Sites (direct owner) | Merchant domains, verification, tokens, settlement caps |
| `affiliate_network_offer_categories` | Catalog (direct owner) | Hierarchical categories |
| `affiliate_network_offers` | Catalog (`offer.site`) | Offers, rates, caps, promotions, restrictions |
| `affiliate_network_offer_creatives` | Catalog (`offer.site`) | Banners, text, email, HTML, video, docs |
| `affiliate_network_offer_applications` | Enrollment (`affiliate`) | Creator applications and review state |
| `affiliate_network_offer_invitations` | Enrollment (`offer.site`) | Tokenized private/unlisted invitations |
| `affiliate_network_offer_deals` | Enrollment (`offer.site`) | Per-affiliate rate/fee overrides |
| `affiliate_network_offer_coupons` | Attribution (`offer.site`) | Affiliate-assigned promo codes |
| `affiliate_network_offer_links` | Attribution (`affiliate`) | Tracking links + click/conversion counters |
| `affiliate_network_conversion_legs` | Ledger (`affiliate`, fallback `offer.site`) | Append-only money legs, adjustments, reversals |
| `affiliate_network_creator_postbacks` | Postbacks | Creator webhook endpoint configuration |
| `affiliate_network_postback_deliveries` | Postbacks | Per-event delivery attempts |
| `affiliate_network_merchant_statements` | Ledger (direct owner) | Settlement statements per site/currency/period |

## Discovery, enrollment, and conversion boundaries

`affiliate-network` owns discovery and the network money books.
`affiliates` owns merchant-local execution. The two books meet only at
fulfillment: a posted leg goes to exactly one payer.

- Conversion precedence is split: the network records link/coupon attribution
  and network legs; the engine records merchant commission and payout state.
- `RecordNetworkConversion` requires a non-empty `externalReference` and is
  idempotent on `(link|coupon, reference, event_type)`.
- Catalog sync mirrors offers with `source = mirrored`; operator rate edits flip
  the offer to `manual` and later syncs hold rates back (`locked`).

## Requirements

- PHP 8.5+
- Laravel 13+
- `aiarmada/commerce-support` (owner scoping, money, HTTP guards)
- `aiarmada/links` (public tracked redirects)
- Spatie Media Library v11 (creative assets)
- `aiarmada/affiliates` (optional; engine identity/ledger/fulfillment/catalog)

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Models](05-models.md)
- [Services](06-services.md)
- [Multi-tenancy](07-multi-tenancy.md)
- [API reference](08-api-reference.md)
- [Testing and factories](09-testing-factories.md)
- [Merchant postbacks](10-merchant-postbacks.md)
- [Merchant self-service](11-merchant-self-service.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Affiliate Network overview](../../filament-affiliate-network/docs/01-overview.md)
