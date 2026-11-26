---
title: Configuration
---

# Configuration

The package configuration lives at `config/affiliate-network.php`. Publish it
with:

```bash
php artisan vendor:publish --tag=affiliate-network-config
```

## Full Configuration Reference

```php
<?php

declare(strict_types=1);

$tablePrefix = 'affiliate_network_';

$tables = [
    'sites' => $tablePrefix . 'sites',
    'offers' => $tablePrefix . 'offers',
    'offer_categories' => $tablePrefix . 'offer_categories',
    'offer_creatives' => $tablePrefix . 'offer_creatives',
    'offer_applications' => $tablePrefix . 'offer_applications',
    'offer_invitations' => $tablePrefix . 'offer_invitations',
    'offer_deals' => $tablePrefix . 'offer_deals',
    'offer_coupons' => $tablePrefix . 'offer_coupons',
    'offer_links' => $tablePrefix . 'offer_links',
    'conversion_legs' => $tablePrefix . 'conversion_legs',
    'creator_postbacks' => $tablePrefix . 'creator_postbacks',
    'postback_deliveries' => $tablePrefix . 'postback_deliveries',
    'merchant_statements' => $tablePrefix . 'merchant_statements',
];

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'tables' => $tables,
        'json_column_type' => env('AFFILIATE_NETWORK_JSON_COLUMN_TYPE', 'jsonb'),
    ],

    'models' => [
        'affiliate' => null, // bound by aiarmada/affiliates, or your own affiliate model
    ],

    'owner' => [
        'enabled' => env('AFFILIATE_NETWORK_OWNER_ENABLED', false),
        'include_global' => env('AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL', false),
    ],

    'currency' => [
        'default' => env('AFFILIATE_NETWORK_DEFAULT_CURRENCY', 'MYR'),
    ],

    'fees' => [
        'default_bp' => env('AFFILIATE_NETWORK_FEE_BP', 0),
    ],

    'locking' => [
        'default_days' => env('AFFILIATE_NETWORK_LOCKING_DAYS', 0),
    ],

    'statements' => [
        'due_days' => env('AFFILIATE_NETWORK_STATEMENT_DUE_DAYS', 30),
    ],

    'notifications' => [
        'enabled' => env('AFFILIATE_NETWORK_NOTIFICATIONS_ENABLED', true),
    ],

    'applications' => [
        'auto_approve' => env('AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE', false),
        'cooldown_days' => env('AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS', 7),
    ],

    'links' => [
        'default_style' => 'short',
        'parameter' => env('AFFILIATE_NETWORK_LINK_PARAM', 'anl'),
        'default_fallback_url' => env('AFFILIATE_NETWORK_DEFAULT_FALLBACK_URL'),
    ],

    'cookies' => [
        'enabled' => env('AFFILIATE_NETWORK_COOKIE_ENABLED', true),
        'name' => env('AFFILIATE_NETWORK_COOKIE_NAME', 'affiliate_network_link'),
        'query_parameters' => ['anl'],
        'ttl_minutes' => env('AFFILIATE_NETWORK_COOKIE_LIFETIME', 60 * 24 * 30),
        'path' => env('AFFILIATE_NETWORK_COOKIE_PATH', '/'),
        'domain' => env('AFFILIATE_NETWORK_COOKIE_DOMAIN'),
        'secure' => env('AFFILIATE_NETWORK_COOKIE_SECURE', true),
        'http_only' => env('AFFILIATE_NETWORK_COOKIE_HTTP_ONLY', true),
        'same_site' => env('AFFILIATE_NETWORK_COOKIE_SAMESITE', 'lax'),
        'respect_dnt' => env('AFFILIATE_NETWORK_COOKIE_RESPECT_DNT', false),
    ],

    'checkout' => [
        'enabled' => env('AFFILIATE_NETWORK_CHECKOUT_ENABLED', false),
        'middleware_group' => env('AFFILIATE_NETWORK_MIDDLEWARE_GROUP', 'web'),
        'listen_for_orders' => env('AFFILIATE_NETWORK_LISTEN_ORDERS', true),
        'attribution_window_hours' => env('AFFILIATE_NETWORK_ATTRIBUTION_WINDOW_HOURS', 720),
    ],

    'sync' => [
        'enabled' => env('AFFILIATE_NETWORK_SYNC_ENABLED', true),
        'max_subjects' => env('AFFILIATE_NETWORK_SYNC_MAX_SUBJECTS', 500),
        'max_programs' => env('AFFILIATE_NETWORK_SYNC_MAX_PROGRAMS', 100),
    ],

    'postbacks' => [
        'enabled' => env('AFFILIATE_NETWORK_POSTBACKS_ENABLED', false),
        'prefix' => env('AFFILIATE_NETWORK_POSTBACKS_PREFIX', 'api/affiliate-network'),
        'middleware' => ['api', 'throttle:60,1'],
    ],

    'creator_postbacks' => [
        'enabled' => env('AFFILIATE_NETWORK_CREATOR_POSTBACKS_ENABLED', true),
        'queue' => env('AFFILIATE_NETWORK_CREATOR_POSTBACKS_QUEUE'),
        'max_attempts' => 3,
        'backoff_seconds' => [10, 60, 300],
    ],

    'http' => [
        'connect_timeout_seconds' => 3,
        'timeout_seconds' => 5,
        'retries' => 1,
        'retry_sleep_ms' => 150,
        'max_response_bytes' => 1024 * 1024,
    ],
];
```

## Configuration Sections

### Database

| Key | Description | Default |
|-----|-------------|---------|
| `table_prefix` | Prefix prepended to every table name | `affiliate_network_` |
| `tables` | Map of the 13 logical table keys to physical names | see above |
| `json_column_type` | JSON column type for array columns (`json`/`jsonb`) | `AFFILIATE_NETWORK_JSON_COLUMN_TYPE`, default `jsonb` |

The 13 `tables` keys are: `sites`, `offers`, `offer_categories`,
`offer_creatives`, `offer_applications`, `offer_invitations`, `offer_deals`,
`offer_coupons`, `offer_links`, `conversion_legs`, `creator_postbacks`,
`postback_deliveries`, `merchant_statements`.

`database.json_column_type` is read by every migration that declares an array
column (`volume_tiers`, `event_rates`, `restrictions`, `metadata`, `settings`,
`events`, and so on). Never hardcode `json`/`jsonb` elsewhere.

### Models

Each `FeatureModule` registers its internal relation keys at boot:

| Key | Bound by | Purpose |
|-----|----------|---------|
| `models.site` | Sites feature | `AffiliateSite` |
| `models.offer` | Catalog feature | `AffiliateOffer` |
| `models.offer_category` | Catalog feature | `AffiliateOfferCategory` |
| `models.offer_creative` | Catalog feature | `AffiliateOfferCreative` |
| `models.offer_application` | Enrollment feature | `AffiliateOfferApplication` |
| `models.offer_invitation` | Enrollment feature | `AffiliateOfferInvitation` |
| `models.offer_deal` | Enrollment feature | `AffiliateOfferDeal` |
| `models.offer_link` | Attribution feature | `AffiliateOfferLink` |
| `models.offer_coupon` | Attribution feature | `AffiliateOfferCoupon` |
| `models.conversion_leg` | Ledger feature | `NetworkConversionLeg` |
| `models.merchant_statement` | Ledger feature | `MerchantStatement` |
| `models.creator_postback` | Postbacks feature | `CreatorPostbackEndpoint` |
| `models.postback_delivery` | Postbacks feature | `CreatorPostbackDelivery` |

Affiliate identity and its optional owner-table projection are supplied by `AffiliateIdentityResolver`, not an Eloquent model configuration.

### Owner (Multi-Tenancy)

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable owner scoping | `false` |
| `include_global` | Include global (`owner_type`/`owner_id` null) rows | `false` |

See [Multi-tenancy](07-multi-tenancy.md) for the full model-by-model scoping map.

### Currency

| Key | Description | Default |
|-----|-------------|---------|
| `default` | Fallback ISO currency for reporting and currency-less records | `MYR` |

### Fees

| Key | Description | Default |
|-----|-------------|---------|
| `default_bp` | Marketplace take-rate in basis points on commission; offers override via `network_fee_bp` | `0` |

The fee is basis points on **commission** (not revenue): `payout = commission - fee`.

### Locking

| Key | Description | Default |
|-----|-------------|---------|
| `default_days` | Fallback locking window when the offer and site set none | `0` |

Precedence: `offer.locking_days` → `site.default_locking_days` → this value.
A non-zero window makes new posted conversions land as `pending` legs with a
`locks_at` deadline.

### Statements

| Key | Description | Default |
|-----|-------------|---------|
| `due_days` | Days from issuance to `due_at` on a merchant statement | `30` |

### Notifications

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Managed application/conversion notifications via `aiarmada/communications` (silent when absent) | `true` |

### Applications

| Key | Description | Default |
|-----|-------------|---------|
| `auto_approve` | Auto-approve every application | `false` |
| `cooldown_days` | Days before a rejected affiliate may reapply | `7` |

An offer with `requires_approval = false` shares the auto-approval path.

### Deep Links

| Key | Description | Default |
|-----|-------------|---------|
| `default_style` | `short` or `branded` link style | `short` |
| `parameter` | Query parameter carrying the network slug | `anl` |
| `default_fallback_url` | Last-resort redirect target when an offer/site exposes none | `null` |

`CreateOfferLink::execute()` accepts `link_style` and `link_label` to override
the default per call. Handles come from `AffiliateIdentityResolver`; the
default user resolver reads `handle` or assigns a stable `creator-{hash}`
handle. Issued URLs keep their stored handle snapshot.

### Cookies

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable referral cookie tracking on internal sites | `true` |
| `name` | Attribution cookie name | `affiliate_network_link` |
| `query_parameters` | Query params checked for network links | `['anl']` |
| `ttl_minutes` | Cookie lifetime | `43200` (30 days) |
| `path` | Cookie path | `/` |
| `domain` | Cookie domain override | `null` |
| `secure` | Set `Secure` | `true` |
| `http_only` | Set `HttpOnly` | `true` |
| `same_site` | `SameSite` attribute | `lax` |
| `respect_dnt` | Honor Do Not Track | `false` |

### Checkout Integration

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable tracking/conversion on checkout sites | `false` |
| `middleware_group` | Middleware group for the tracking middleware | `web` |
| `listen_for_orders` | Record conversions from orders | `true` |
| `attribution_window_hours` | Link attribution window | `720` |

### Sync (Catalog Mirror)

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable catalog sync | `true` |
| `max_subjects` | Imported subjects per program sync | `500` |
| `max_programs` | Programs per `syncAll` run | `100` |

### Merchant Postbacks (remote merchant → network)

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Expose the merchant conversion endpoints | `false` |
| `prefix` | API prefix for postback routes | `api/affiliate-network` |
| `middleware` | Route middleware | `['api', 'throttle:60,1']` |

### Creator Postbacks (network → creator)

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enqueue outbound creator postbacks | `true` |
| `queue` | Queue name for delivery jobs (null = default) | `null` |
| `max_attempts` | Delivery attempts before marking failed | `3` |
| `backoff_seconds` | Retry backoff schedule (documented; jobs use queue retry) | `[10, 60, 300]` |

Endpoints are signed with `X-Affiliate-Network-Signature` =
`hash_hmac('sha256', $resolvedUrl, $secret)` when a signing secret is set.

### HTTP

| Key | Description | Default |
|-----|-------------|---------|
| `connect_timeout_seconds` | Connection timeout | `3` |
| `timeout_seconds` | Request timeout | `5` |
| `retries` | Retry attempts | `1` |
| `retry_sleep_ms` | Delay between retries | `150` |
| `max_response_bytes` | Maximum catalog/site/postback response body | `1048576` (1 MiB) |

Site-content, catalog, and creator-postback requests always use the shared
public-URL guard: HTTP/HTTPS only, public DNS/IPs, no credentials/fragments,
pinned transport with redirects disabled, timeouts/retries, and bounded
response bodies. This validation cannot be disabled through configuration
(see `Platform\DomainHostValidator` and `commerce-support`'s
`PublicHttpUrlGuard`).

## Verified Defaults

- The default `json_column_type` is `jsonb`; set `AFFILIATE_NETWORK_JSON_COLUMN_TYPE=json` for databases without `jsonb`.
- The default network fee is `0`; the network ships rate-agnostic until operators set a take-rate.
- The default locking window is `0`; posted conversions fulfil immediately unless configured.
