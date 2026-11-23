---
title: Installation
---

# Installation

> **warning**
> Breaking change: affiliate sites and offers no longer provide the Contacting
> package's contact method or social profile relationships. Before upgrading,
> move any attached records to the merchant's contactable/socialable models
> using Contacting. Deleting a site or offer afterwards leaves legacy
> attachments in place.

## Requirements

- PHP 8.5+
- Laravel 13+
- `aiarmada/commerce-support` (owner scoping, money, HTTP guards)
- `aiarmada/links` (public tracked redirects)
- Spatie Media Library v11 (creative assets; `intervention/image` only if you convert formats)
- `aiarmada/affiliates` (optional; binds the engine identity, ledger, fulfillment, and catalog adapters)

## Install via Composer

```bash
composer require aiarmada/affiliate-network
```

For shared-DB installs that resolve affiliate identity, post merchant
fulfillment, or mirror local catalogs, also require the engine:

```bash
composer require aiarmada/affiliates
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=affiliate-network-config
```

## Run Migrations

```bash
php artisan migrate
```

The package ships **13** migrations (prefix configurable via
`database.tables` / `database.table_prefix`):

| # | Migration | Table |
|---|---|---|
| `000001` | `create_affiliate_network_sites_table` | `affiliate_network_sites` |
| `000002` | `create_affiliate_network_offer_categories_table` | `affiliate_network_offer_categories` |
| `000003` | `create_affiliate_network_offers_table` | `affiliate_network_offers` |
| `000004` | `create_affiliate_network_offer_creatives_table` | `affiliate_network_offer_creatives` |
| `000005` | `create_affiliate_network_offer_applications_table` | `affiliate_network_offer_applications` |
| `000006` | `create_affiliate_network_offer_links_table` | `affiliate_network_offer_links` |
| `000007` | `create_affiliate_network_conversion_legs_table` | `affiliate_network_conversion_legs` |
| `000008` | `create_affiliate_network_offer_invitations_table` | `affiliate_network_offer_invitations` |
| `000009` | `create_affiliate_network_offer_deals_table` | `affiliate_network_offer_deals` |
| `000010` | `create_affiliate_network_offer_coupons_table` | `affiliate_network_offer_coupons` |
| `000011` | `create_affiliate_network_creator_postbacks_table` | `affiliate_network_creator_postbacks` |
| `000012` | `create_affiliate_network_postback_deliveries_table` | `affiliate_network_postback_deliveries` |
| `000013` | `create_affiliate_network_merchant_statements_table` | `affiliate_network_merchant_statements` |

Manual creative files use the `public` disk. `commerce-support` loads the Media
Library migration when no published copy exists, with UUID morph keys matching
offer-creative IDs. Configure media storage through `config/media-library.php`.
Imported creatives hotlink public merchant assets instead of copying bytes.

## Environment Variables

```env
# JSON column type (json or jsonb for PostgreSQL)
AFFILIATE_NETWORK_JSON_COLUMN_TYPE=jsonb

# Multi-tenancy
AFFILIATE_NETWORK_OWNER_ENABLED=false
AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL=false

# Defaults / fees
AFFILIATE_NETWORK_DEFAULT_CURRENCY=MYR
AFFILIATE_NETWORK_FEE_BP=0

# Locking / statements
AFFILIATE_NETWORK_LOCKING_DAYS=0
AFFILIATE_NETWORK_STATEMENT_DUE_DAYS=30

# Applications
AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE=false
AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS=7

# Links / cookies
AFFILIATE_NETWORK_LINK_PARAM=anl
AFFILIATE_NETWORK_DEFAULT_FALLBACK_URL=
AFFILIATE_NETWORK_COOKIE_ENABLED=true
AFFILIATE_NETWORK_COOKIE_NAME=affiliate_network_link
AFFILIATE_NETWORK_COOKIE_LIFETIME=43200
AFFILIATE_NETWORK_COOKIE_PATH=/
AFFILIATE_NETWORK_COOKIE_DOMAIN=
AFFILIATE_NETWORK_COOKIE_SECURE=true
AFFILIATE_NETWORK_COOKIE_HTTP_ONLY=true
AFFILIATE_NETWORK_COOKIE_SAMESITE=lax
AFFILIATE_NETWORK_COOKIE_RESPECT_DNT=false

# Checkout integration
AFFILIATE_NETWORK_CHECKOUT_ENABLED=false
AFFILIATE_NETWORK_MIDDLEWARE_GROUP=web
AFFILIATE_NETWORK_LISTEN_ORDERS=true
AFFILIATE_NETWORK_ATTRIBUTION_WINDOW_HOURS=720

# Notifications
AFFILIATE_NETWORK_NOTIFICATIONS_ENABLED=true

# Catalog sync
AFFILIATE_NETWORK_SYNC_ENABLED=true
AFFILIATE_NETWORK_SYNC_MAX_SUBJECTS=500
AFFILIATE_NETWORK_SYNC_MAX_PROGRAMS=100

# Merchant postbacks (remote merchants → network)
AFFILIATE_NETWORK_POSTBACKS_ENABLED=false
AFFILIATE_NETWORK_POSTBACKS_PREFIX=api/affiliate-network

# Outbound creator postbacks (network → creators)
AFFILIATE_NETWORK_CREATOR_POSTBACKS_ENABLED=true
AFFILIATE_NETWORK_CREATOR_POSTBACKS_QUEUE=
```

## Service Provider

The package auto-registers via Laravel package discovery. It loads each
feature's `feature.json` through `Platform\FeatureCatalog`, binds the feature
APIs, and registers the `site.verification_strategy` extension slot.

Registered API bindings:

- `Features\Sites\Api\SitesApi` — site lookup, verification state, sync status, prepaid balance
- `Features\Catalog\Api\CatalogApi` — offer/creative reads, fallback resolution, restrictions, promotions
- `Features\Enrollment\Api\EnrollmentApi` — application/approval state and deal overrides
- `Features\Attribution\Api\AttributionApi` — link/coupon resolution and counters
- `Features\Ledger\Api\LedgerApi` — cap checks, leg lookups, conversion metrics
- `Features\Postbacks\Api\PostbacksApi` — creator postback configuration and delivery status

## Routes

Redirects are served by `aiarmada/links` (`GET /go/{slug}`). This package
registers the merchant conversion routes when `postbacks.enabled` is true:

- `POST {postbacks.prefix}/conversions`
- `POST {postbacks.prefix}/conversions/void`
- `POST {postbacks.prefix}/conversions/adjust`

## Verification Steps

After installation, prove the package boundaries and types are intact. Run
these from the repository root (monorepo) or the package root (standalone):

```bash
# AST boundary + single-writer ownership (0 violations expected)
php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network

# Package-scoped static analysis (0 errors expected)
./vendor/bin/phpstan analyse -c packages/affiliate-network/tools/package-phpstan.neon \
  --autoload-file=packages/affiliate-network/tools/Architecture/PackageOwnedWriteRule.php \
  --no-progress

# Scoped formatting
./vendor/bin/pint packages/affiliate-network

# Package tests
./vendor/bin/pest --parallel tests/src/AffiliateNetwork
```

## Laravel Boost discovery

The package ships downstream AI context for host applications using Laravel
Boost:

- `resources/boost/guidelines/core.blade.php` — auto-loaded package guideline
- `resources/boost/skills/affiliate-network-development/SKILL.md` — on-demand skill

Refresh the downstream assets after public API changes. In a consumer app, Boost
discovers them automatically once the package is installed; no manual symlink is
required.

## Next Steps

1. Configure [settings](03-configuration.md)
2. Create and verify merchant sites
3. Publish offers
4. Wire enrollment, links, and the money ledger
5. Add the Filament UI with `filament-affiliate-network`
