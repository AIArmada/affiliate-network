---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- `aiarmada/affiliates` package (for affiliate relationship)
- `aiarmada/commerce-support` package (for owner traits)

## Install via Composer

```bash
composer require aiarmada/affiliate-network
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=affiliate-network-config
```

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables (with configurable prefix):

- `affiliate_network_sites` - Merchant sites/domains
- `affiliate_network_offer_categories` - Offer categories
- `affiliate_network_offers` - Affiliate offers
- `affiliate_network_offer_creatives` - Banners, text links, etc.
- `affiliate_network_offer_applications` - Affiliate applications
- `affiliate_network_offer_links` - Tracking links

## Environment Variables

```env
# Table prefix (default: affiliate_network_)
AFFILIATE_NETWORK_TABLE_PREFIX=affiliate_network_

# JSON column type (json or jsonb for PostgreSQL)
AFFILIATE_NETWORK_JSON_COLUMN_TYPE=json

# Multi-tenancy
AFFILIATE_NETWORK_OWNER_ENABLED=false
AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL=false
AFFILIATE_NETWORK_OWNER_AUTO_ASSIGN=true

# Site verification
AFFILIATE_NETWORK_SITES_REQUIRE_VERIFICATION=true
AFFILIATE_NETWORK_MAX_SITES=10

# Offers
AFFILIATE_NETWORK_OFFERS_REQUIRE_APPROVAL=true
AFFILIATE_NETWORK_OFFERS_PUBLIC=true

# Applications
AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE=false
AFFILIATE_NETWORK_APPLICATIONS_REQUIRE_REASON=false
AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS=7

# Link signing
AFFILIATE_NETWORK_LINK_SIGNING_KEY=
AFFILIATE_NETWORK_LINK_TTL=43200
AFFILIATE_NETWORK_LINK_PARAM=anl

# Marketplace
AFFILIATE_NETWORK_FEATURED_LIMIT=10
AFFILIATE_NETWORK_CATEGORY_DEPTH=3
AFFILIATE_NETWORK_SEARCH_ENABLED=true
```

## Service Provider

The package auto-registers via Laravel's package discovery.

Registered services:
- `SiteVerificationService` - DNS/meta/file verification
- `OfferManagementService` - Offer CRUD and applications
- `OfferLinkService` - Tracking link generation

## Routes

The package registers a link redirect route:

```
GET /affiliate-network/go/{code}
```

This handles click tracking and redirects to the target URL.

## Next Steps

1. Configure [settings](03-configuration.md)
2. Create merchant sites
3. Publish offers
4. Set up the Filament UI with `filament-affiliate-network`
