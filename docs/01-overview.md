---
title: Overview
---

# Affiliate Network Package

## Purpose

The `aiarmada/affiliate-network` package is a standalone multi-merchant affiliate marketplace with sites, offers, applications, creatives, and tracking links. It never requires `aiarmada/affiliates`; when that package is installed it binds local adapters for affiliate identity, the conversion ledger, core programs, and catalog sync.

> [!WARNING]
> Breaking change: the network seam. Public APIs take affiliate IDs (`string`) instead of `Affiliate` models (`applyForOffer()`, `createLink()`, `isApprovedForOffer()`, `getApprovedOffers()`, and friends), and factories use `forAffiliateId()`. Every offer — mirrored or hand-written — uses the network application flow: the program-membership bridge (`LinkedProgramBridge`, `membershipsForPrograms()`, `enrollInLinkedProgram()`, `isLocalProgramOffer()`) is removed. Migration: pass `(string) $affiliate->getKey()` at call sites and read application state through the network rows. No data migration: `affiliate_id` columns are unchanged.

## What this package owns

- Merchant sites and site verification workflows
- Affiliate offers, categories, creatives, applications, and network tracking links
- Marketplace discovery flows and merchant/offer management services
- Network-specific checkout attribution boundary and offer-level aggregated metrics

## What this package does not own

- Core affiliate attribution, commissions, payouts, or fraud models; those stay in `aiarmada/affiliates`
- Merchant-local commission execution; enrollment for every offer — mirrored or hand-written — is a network application. Joining never requires, resolves, or creates a merchant-side account
- Filament marketplace/admin surfaces; those belong to `aiarmada/filament-affiliate-network`
- General checkout, cart, or order persistence beyond its integration hooks

## Related packages

- [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — optional: binds the local identity, ledger, program, and catalog adapters behind the network seam
- [`aiarmada/filament-affiliate-network`](../../filament-affiliate-network/docs/01-overview.md) — Filament marketplace and merchant admin surfaces
- [`aiarmada/filament-affiliates`](../../filament-affiliates/docs/01-overview.md) — complementary affiliate admin and portal UI
- [`aiarmada/checkout`](../../checkout/docs/01-overview.md) — optional conversion recording bridge for sites using the Commerce checkout stack

## Main models services or surfaces

- **Models** — `AffiliateSite`, `AffiliateOffer`, `AffiliateOfferCategory`, `AffiliateOfferCreative`, `AffiliateOfferApplication`, `AffiliateOfferLink`
- **Actions** — `CreateOffer`, `UpdateOffer`, `ApplyToOffer`, `ApproveApplication`, `RecordNetworkConversion`
- **Contracts** — `SiteVerificationStrategyInterface` (DNS, meta tag, file verification strategies); the network seam `AffiliateIdentityResolver`, `NetworkLedger` plus `CatalogReaderInterface`
- **Services** — site verification, offer management, and offer link generation/tracking
- **Events** — `OfferCreated`, `OfferUpdated`, `ApplicationSubmitted`, `ApplicationApproved`, `NetworkConversionRecorded`
- **Exceptions** — `OfferNotFoundException`, `ApplicationAlreadySubmittedException`, `SiteVerificationFailedException`, `AffiliatesNotInstalled` (seam feature used without an adapter)
- **Routes and middleware** — merchant postbacks and cookie-tracking flows; redirects ride on `aiarmada/links`

## Owner scoping and security notes

- Site and category records are owner-aware, while offers and creatives inherit through `site` and `offer.site`; applications and links inherit through `affiliate`
- `ScopesByBelongsToOwner` delegates owner predicates, explicit-global handling, and write semantics to commerce-support's `OwnerScope`; it is the only relationship-based scope trait in this package
- Tracking metrics remain inside the affiliate-network boundary and should not be assumed to match core affiliates conversion schemas without an explicit application bridge
- Offer, site, and application identifiers should still be resolved inside the current owner or relationship scope on write paths

## Discovery, enrollment, and conversion boundaries

`affiliate-network` owns discovery: merchant sites, marketplace offers, signed-redirect policy, clicks, and network-level applications for remote catalogs. `affiliates` owns merchant-local execution: `AffiliateProgram`, memberships, attribution, commissions, payouts, and fraud decisions. The network never writes commission or payout records.

Local catalog synchronization calls the read-only `ProgramCatalogService::snapshot()` path through the affiliates-provided local reader. A local imported offer keeps the core program ID in `external_program_id` for reference, but enrollment for every offer is a network application — remote mirrors are marked with `metadata.catalog_source = remote`, and neither path touches merchant program memberships.

Conversion precedence is intentionally split: the network side records discovery attribution (link clicks/conversions and `network_attribution` order metadata), while core `affiliates` records commission and payout state. Keep the guards separate when both paths observe one order: the network integration must reject an already-attributed order/link before recording a second network conversion, and core conversion calls must carry a stable `external_reference`, which `RecordAffiliateConversion` turns into its idempotency key. Core commission data is authoritative for commission and payout execution; network click/conversion counters remain discovery reporting. The `orders` listener is an integration boundary and is not replaced or modified by this package.

Public redirects are served by `aiarmada/links` (`GET /go/{slug}`): per-link signed URLs with `links.routing.signature_ttl_minutes` TTL, a network policy gate over link, offer, site, and approval state, and throttling through `links.routing.middleware`. Outbound catalog and verification HTTP uses the shared public-URL guard (HTTP/HTTPS only, public DNS/IPs, no credentials/fragments), pinned transport with redirects disabled, configured timeouts/retries, and a one-megabyte response cap.

The `aiarmada/affiliate-network` package provides a complete multi-merchant affiliate network and marketplace system for Laravel. It runs standalone so merchants can publish offers and affiliates can discover and promote them; install `aiarmada/affiliates` alongside it to enable local identity, ledger, program, and catalog features.

## Key Features

- **Site Management** - Merchants register and verify their domains via DNS, meta tag, or file verification
- **Offer Publishing** - Create affiliate offers with flexible commission structures (percentage or fixed)
- **Offer Categories** - Hierarchical category organization with configurable depth
- **Offer Applications** - Affiliates apply to promote offers with approval workflows
- **Tracking Links** - Signed deep link generation with click/conversion tracking and sub-ID support
- **Creative Assets** - Banners, text links, email templates, HTML widgets, and video content
- **Checkout Integration** - Native tracking and conversion recording for sites using the commerce checkout package
- **Multi-Tenancy** - Full owner scoping with relationship-based inheritance
- **Marketplace** - Public offer discovery with featured listings and search

## Use Cases

### Affiliate Marketplace

Build an affiliate marketplace where merchants list offers and affiliates browse/apply:

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;

// Get active public offers for marketplace display
$offers = AffiliateOffer::query()
    ->where('status', AffiliateOffer::STATUS_ACTIVE)
    ->where('is_public', true)
    ->orderByDesc('is_featured')
    ->orderByDesc('created_at')
    ->with(['site', 'category', 'creatives'])
    ->get();
```

### Private Affiliate Network

Run a private network with invite-only offers:

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;

// Private offers requiring manual approval
$offers = AffiliateOffer::query()
    ->where('requires_approval', true)
    ->where('is_public', false)
    ->where('status', AffiliateOffer::STATUS_ACTIVE)
    ->get();
```

### Multi-Merchant Platform

Host multiple merchants, each managing their own sites and offers:

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

// Sites are automatically scoped to current owner when enabled
$merchantSites = AffiliateSite::query()
    ->where('status', AffiliateSite::STATUS_VERIFIED)
    ->withCount('offers')
    ->get();

// Or explicitly query for a specific owner
$sites = AffiliateSite::forOwner($merchant)->get();
```

### Deep Link Tracking

Generate signed tracking URLs with full attribution:

```php
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

$linkService = app(OfferLinkService::class);

$link = $linkService->createLink($offer, (string) $affiliate->getKey(), [
    'sub_id' => 'blog-post-1',
    'sub_id_2' => 'sidebar',
    'sub_id_3' => 'banner-728x90',
]);

$trackingUrl = $linkService->generateTrackingUrl($link);
// https://yoursite.com/go/aB3dE9fHjKlmN0p?signature=xxx&expires=xxx
```

Redirects are served by `aiarmada/links`; the network contributes redirect policy (link, offer, site, and approval state) through a link gate.

## Architecture

```
affiliate-network/
├── config/
│   └── affiliate-network.php        # Package configuration
├── database/
│   ├── factories/                   # 6 model factories
│   ├── migrations/                  # 7 migration files
├── routes/
│   └── api.php                      # Merchant postback route
└── src/
    ├── Actions/
    │   ├── ApplyToOffer.php              # Apply to an offer
    │   ├── ApproveApplication.php        # Approve/reject applications
    │   ├── CreateOffer.php               # Create a new offer
    │   ├── RecordNetworkConversion.php   # Record a conversion
    │   └── UpdateOffer.php               # Update an existing offer
    ├── Console/Commands/
    │   └── ArchiveExpiredOffersCommand.php # Batch archive expired offers
    ├── Contracts/
    │   └── SiteVerificationStrategyInterface.php
    ├── Events/
    │   ├── ApplicationApproved.php
    │   ├── ApplicationSubmitted.php
    │   ├── NetworkConversionRecorded.php
    │   ├── OfferCreated.php
    │   └── OfferUpdated.php
    ├── Exceptions/
    │   ├── ApplicationAlreadySubmittedException.php
    │   ├── OfferNotFoundException.php
    │   └── SiteVerificationFailedException.php
    ├── Http/
    │   ├── Controllers/
    │   │   └── ReportNetworkConversionController.php
    │   └── Middleware/
    │       └── TrackNetworkLinkCookie.php
    ├── Listeners/
    │   ├── IncrementNetworkLinkClicks.php
    │   └── RecordNetworkConversionForOrder.php
    ├── Models/
    │   ├── AffiliateSite.php
    │   ├── AffiliateOffer.php
    │   ├── AffiliateOfferCategory.php
    │   ├── AffiliateOfferCreative.php
    │   ├── AffiliateOfferApplication.php
    │   ├── AffiliateOfferLink.php
    │   └── Concerns/
    │       └── ScopesByBelongsToOwner.php
    ├── Services/
    │   ├── SiteVerificationService.php
    │   ├── OfferManagementService.php
    │   └── OfferLinkService.php
    ├── Strategies/
    │   ├── DnsVerificationStrategy.php
    │   ├── FileVerificationStrategy.php
    │   └── MetaTagVerificationStrategy.php
    └── Support/
        ├── OfferLinkGate.php
        └── SiteContentFetcher.php
```

## Database Schema

| Table | Description | Key Columns |
|-------|-------------|-------------|
| `affiliate_network_sites` | Merchant domains | `owner_type`, `owner_id`, `domain`, `status`, `verification_method` |
| `affiliate_network_offer_categories` | Hierarchical categories | `owner_type`, `owner_id`, `parent_id`, `name`, `slug` |
| `affiliate_network_offers` | Affiliate offers | `site_id`, `category_id`, `rate_base_bp`, `rate_fixed_minor`, `status` |
| `affiliate_network_offer_creatives` | Promotional assets | `offer_id`, `type`, `url`, `width`, `height` |
| `affiliate_network_offer_applications` | Affiliate-to-offer applications | `offer_id`, `affiliate_id`, `status`, `reviewed_at` |
| `affiliate_network_offer_links` | Tracking links | `link_id`, `offer_id`, `affiliate_id`, `clicks`, `conversions`, `revenue`, `currency` |

## Integration with Affiliates Package

This package does **not** require the core `aiarmada/affiliates` package. Integration happens through the network seam (`AffiliateIdentityResolver`, `NetworkLedger`, `CatalogReaderInterface`), which the affiliates package implements when both are installed:

- `AffiliateOfferApplication` and `AffiliateOfferLink` hold opaque `affiliate_id` UUIDs; the `affiliate()` relations resolve the model bound at `affiliate-network.models.affiliate`
- Owner scoping respects affiliate ownership through relationship-based scoping
- Commission structures complement affiliate-level configurations

### Tracking Boundary

`affiliate-network` does **not** depend on the core affiliates package's link or conversion field names such as `external_reference`, `value_minor`, or the subject-aware attribution fields.

Instead, it keeps its own offer-level tracking boundary:

- `AffiliateOfferLink` stores clicks, conversions, and aggregated revenue for marketplace/offer links
- checkout integration stores `network_attribution` in order metadata
- posting into core `aiarmada/affiliates` conversions goes through the `NetworkLedger` seam, bound automatically when that package is installed; without it, conversions keep counters only

## Requirements

- PHP 8.4+
- Laravel 13+
- `aiarmada/commerce-support` package (for owner traits)
- `aiarmada/affiliates` package (optional; enables local identity, ledger, program, and catalog adapters)

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
