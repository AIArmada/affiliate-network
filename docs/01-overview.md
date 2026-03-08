---
title: Overview
---

# Affiliate Network Package

The `aiarmada/affiliate-network` package provides a complete multi-merchant affiliate network and marketplace system for Laravel. It extends the core `aiarmada/affiliates` package to enable merchants to publish offers and affiliates to discover and promote them.

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

$link = $linkService->createLink($offer, $affiliate, [
    'sub_id' => 'blog-post-1',
    'sub_id_2' => 'sidebar',
    'sub_id_3' => 'banner-728x90',
]);

$trackingUrl = $linkService->generateTrackingUrl($link);
// https://yoursite.com/affiliate-network/go/abc123?sig=xxx&expires=xxx
```

## Architecture

```
affiliate-network/
├── config/
│   └── affiliate-network.php        # Package configuration
├── database/
│   ├── factories/                   # 6 model factories
│   │   ├── AffiliateSiteFactory.php
│   │   ├── AffiliateOfferFactory.php
│   │   ├── AffiliateOfferCategoryFactory.php
│   │   ├── AffiliateOfferCreativeFactory.php
│   │   ├── AffiliateOfferApplicationFactory.php
│   │   └── AffiliateOfferLinkFactory.php
│   └── migrations/                  # 6 migration files
├── routes/
│   └── api.php                      # Link redirect route
└── src/
    ├── AffiliateNetworkServiceProvider.php
    ├── Http/
    │   ├── Controllers/
    │   │   └── LinkRedirectController.php
    │   └── Middleware/
    │       └── TrackNetworkLinkCookie.php  # Checkout tracking middleware
    ├── Listeners/
    │   └── RecordNetworkConversionForOrder.php # Order conversion listener
    ├── Models/
    │   ├── AffiliateSite.php              # Merchant sites (HasOwner)
    │   ├── AffiliateOffer.php             # Offers/campaigns
    │   ├── AffiliateOfferCategory.php     # Categories (HasOwner)
    │   ├── AffiliateOfferCreative.php     # Banners/assets
    │   ├── AffiliateOfferApplication.php  # Affiliate applications
    │   ├── AffiliateOfferLink.php         # Tracking links
    │   └── Concerns/
    │       ├── ScopesByAffiliateOwner.php # Scopes via affiliate relationship
    │       └── ScopesBySiteOwner.php      # Scopes via site relationship
    └── Services/
        ├── SiteVerificationService.php    # DNS/meta/file verification
        ├── OfferManagementService.php     # Offer & application CRUD
        └── OfferLinkService.php           # Link generation & tracking
```

## Database Schema

| Table | Description | Key Columns |
|-------|-------------|-------------|
| `affiliate_network_sites` | Merchant domains | `owner_type`, `owner_id`, `domain`, `status`, `verification_method` |
| `affiliate_network_offer_categories` | Hierarchical categories | `owner_type`, `owner_id`, `parent_id`, `name`, `slug` |
| `affiliate_network_offers` | Affiliate offers | `site_id`, `category_id`, `commission_type`, `commission_rate`, `status` |
| `affiliate_network_offer_creatives` | Promotional assets | `offer_id`, `type`, `url`, `width`, `height` |
| `affiliate_network_offer_applications` | Affiliate-to-offer applications | `offer_id`, `affiliate_id`, `status`, `reviewed_at` |
| `affiliate_network_offer_links` | Tracking links | `offer_id`, `affiliate_id`, `code`, `clicks`, `conversions`, `revenue` |

## Integration with Affiliates Package

This package integrates tightly with the core `aiarmada/affiliates` package:

- `AffiliateOfferApplication` links to `Affiliate` model for application tracking
- `AffiliateOfferLink` references `Affiliate` for attribution
- Owner scoping respects affiliate ownership through relationship-based scoping
- Commission structures complement affiliate-level configurations

## Requirements

- PHP 8.4+
- Laravel 12+
- `aiarmada/affiliates` package
- `aiarmada/commerce-support` package (for owner traits)
