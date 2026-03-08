---
title: Configuration
---

# Configuration

The package configuration is located at `config/affiliate-network.php`.

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('AFFILIATE_NETWORK_TABLE_PREFIX', 'affiliate_network_'),
        'json_column_type' => env('AFFILIATE_NETWORK_JSON_COLUMN_TYPE', 'json'),
        'tables' => [
            'sites' => 'affiliate_network_sites',
            'offers' => 'affiliate_network_offers',
            'offer_categories' => 'affiliate_network_offer_categories',
            'offer_creatives' => 'affiliate_network_offer_creatives',
            'offer_applications' => 'affiliate_network_offer_applications',
            'offer_links' => 'affiliate_network_offer_links',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('AFFILIATE_NETWORK_OWNER_ENABLED', false),
        'include_global' => env('AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('AFFILIATE_NETWORK_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sites
    |--------------------------------------------------------------------------
    */
    'sites' => [
        'require_verification' => env('AFFILIATE_NETWORK_SITES_REQUIRE_VERIFICATION', true),
        'verification_methods' => ['dns', 'meta_tag', 'file'],
        'max_sites_per_merchant' => env('AFFILIATE_NETWORK_MAX_SITES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Offers
    |--------------------------------------------------------------------------
    */
    'offers' => [
        'require_approval' => env('AFFILIATE_NETWORK_OFFERS_REQUIRE_APPROVAL', true),
        'default_status' => 'pending',
        'allow_public_listing' => env('AFFILIATE_NETWORK_OFFERS_PUBLIC', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Applications
    |--------------------------------------------------------------------------
    */
    'applications' => [
        'auto_approve' => env('AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE', false),
        'require_reason' => env('AFFILIATE_NETWORK_APPLICATIONS_REQUIRE_REASON', false),
        'cooldown_days' => env('AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep Links
    |--------------------------------------------------------------------------
    */
    'links' => [
        'signing_key' => env('AFFILIATE_NETWORK_LINK_SIGNING_KEY', env('APP_KEY')),
        'default_ttl_minutes' => env('AFFILIATE_NETWORK_LINK_TTL', 60 * 24 * 30),
        'parameter' => env('AFFILIATE_NETWORK_LINK_PARAM', 'anl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookies
    |--------------------------------------------------------------------------
    */
    'cookies' => [
        'name' => env('AFFILIATE_NETWORK_COOKIE_NAME', 'affiliate_network_link'),
        'lifetime_minutes' => env('AFFILIATE_NETWORK_COOKIE_LIFETIME', 60 * 24 * 30),
        'secure' => env('AFFILIATE_NETWORK_COOKIE_SECURE', true),
        'same_site' => env('AFFILIATE_NETWORK_COOKIE_SAMESITE', 'lax'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout Integration
    |--------------------------------------------------------------------------
    */
    'checkout' => [
        'enabled' => env('AFFILIATE_NETWORK_CHECKOUT_ENABLED', false),
        'middleware_group' => env('AFFILIATE_NETWORK_MIDDLEWARE_GROUP', 'web'),
        'listen_for_orders' => env('AFFILIATE_NETWORK_LISTEN_ORDERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    */
    'marketplace' => [
        'featured_offers_limit' => env('AFFILIATE_NETWORK_FEATURED_LIMIT', 10),
        'category_depth' => env('AFFILIATE_NETWORK_CATEGORY_DEPTH', 3),
        'search_enabled' => env('AFFILIATE_NETWORK_SEARCH_ENABLED', true),
    ],
];
```

## Configuration Sections

### Database

| Key | Description | Default |
|-----|-------------|---------|
| `table_prefix` | Prefix for all tables | `affiliate_network_` |
| `json_column_type` | JSON column type (json/jsonb) | `json` |
| `tables` | Table name mapping | Array |

### Owner (Multi-Tenancy)

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable owner scoping | `false` |
| `include_global` | Include global (null owner) records | `false` |
| `auto_assign_on_create` | Auto-assign owner on create | `true` |

### Sites

| Key | Description | Default |
|-----|-------------|---------|
| `require_verification` | Require domain verification | `true` |
| `verification_methods` | Available verification methods | `['dns', 'meta_tag', 'file']` |
| `max_sites_per_merchant` | Maximum sites per merchant | `10` |

### Offers

| Key | Description | Default |
|-----|-------------|---------|
| `require_approval` | New offers need approval | `true` |
| `default_status` | Default offer status | `pending` |
| `allow_public_listing` | Allow public marketplace | `true` |

### Applications

| Key | Description | Default |
|-----|-------------|---------|
| `auto_approve` | Auto-approve applications | `false` |
| `require_reason` | Require application reason | `false` |
| `cooldown_days` | Days before reapplying after rejection | `7` |

### Links

| Key | Description | Default |
|-----|-------------|---------|
| `signing_key` | Key for signing URLs | `APP_KEY` |
| `default_ttl_minutes` | Link expiration time | `43200` (30 days) |
| `parameter` | URL parameter name | `anl` |

### Cookies

| Key | Description | Default |
|-----|-------------|---------|
| `name` | Name of the attribution cookie | `affiliate_network_link` |
| `lifetime_minutes` | Cookie expiration time | `43200` (30 days) |
| `secure` | Set secure flag on cookie | `true` |
| `same_site` | SameSite attribute | `lax` |

### Checkout Integration

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable tracking on checkout sites | `false` |
| `middleware_group` | Middleware group for tracking | `web` |
| `listen_for_orders` | Record conversions on orders | `true` |

### Marketplace

| Key | Description | Default |
|-----|-------------|---------|
| `featured_offers_limit` | Max featured offers displayed | `10` |
| `category_depth` | Max category nesting depth | `3` |
| `search_enabled` | Enable marketplace search | `true` |
