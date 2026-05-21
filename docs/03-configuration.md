---
title: Configuration
---

# Configuration

The package configuration is located at `config/affiliate-network.php`.

## Full Configuration Reference

```php
<?php

return [
    'database' => [
        'table_prefix' => 'affiliate_network_',
        'json_column_type' => env('AFFILIATE_NETWORK_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'sites' => 'affiliate_network_sites',
            'offers' => 'affiliate_network_offers',
            'offer_categories' => 'affiliate_network_offer_categories',
            'offer_creatives' => 'affiliate_network_offer_creatives',
            'offer_applications' => 'affiliate_network_offer_applications',
            'offer_links' => 'affiliate_network_offer_links',
        ],
    ],

    'owner' => [
        'enabled' => env('AFFILIATE_NETWORK_OWNER_ENABLED', false),
        'include_global' => env('AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL', false),
    ],

    'offers' => [
        'require_approval' => env('AFFILIATE_NETWORK_OFFERS_REQUIRE_APPROVAL', true),
    ],

    'applications' => [
        'auto_approve' => env('AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE', false),
        'cooldown_days' => env('AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS', 7),
    ],

    'links' => [
        'default_ttl_minutes' => env('AFFILIATE_NETWORK_LINK_TTL', 60 * 24 * 30),
        'parameter' => env('AFFILIATE_NETWORK_LINK_PARAM', 'anl'),
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

    'http' => [
        'connect_timeout_seconds' => 3,
        'timeout_seconds' => 5,
        'retries' => 1,
        'retry_sleep_ms' => 150,
        'skip_dns_check' => false,
    ],
];
```

## Configuration Sections

### Database

| Key | Description | Default |
|-----|-------------|---------|
| `table_prefix` | Prefix for all tables | `affiliate_network_` |
| `json_column_type` | JSON column type (json/jsonb) | `COMMERCE_JSON_COLUMN_TYPE` fallback |
| `tables` | Table name mapping | Array |

### Owner (Multi-Tenancy)

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable owner scoping | `false` |
| `include_global` | Include global (null owner) records | `false` |

### Offers

| Key | Description | Default |
|-----|-------------|---------|
| `require_approval` | New offers need approval | `true` |

### Applications

| Key | Description | Default |
|-----|-------------|---------|
| `auto_approve` | Auto-approve applications | `false` |
| `cooldown_days` | Days before reapplying after rejection | `7` |

### Links

| Key | Description | Default |
|-----|-------------|---------|
| `default_ttl_minutes` | Link expiration time | `43200` (30 days) |
| `parameter` | URL parameter name | `anl` |

### Cookies

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable referral cookie tracking | `true` |
| `name` | Name of the attribution cookie | `affiliate_network_link` |
| `query_parameters` | URL parameters checked for network links | `['anl']` |
| `ttl_minutes` | Cookie expiration time | `43200` (30 days) |
| `path` | Cookie path | `/` |
| `domain` | Cookie domain override | `null` |
| `secure` | Set secure flag on cookie | `true` |
| `http_only` | Set HttpOnly flag | `true` |
| `same_site` | SameSite attribute | `lax` |
| `respect_dnt` | Honor Do Not Track browser headers | `false` |

### Checkout Integration

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Enable tracking on checkout sites | `false` |
| `middleware_group` | Middleware group for tracking | `web` |
| `listen_for_orders` | Record conversions on orders | `true` |
| `attribution_window_hours` | Link attribution window | `720` |

### HTTP

| Key | Description | Default |
|-----|-------------|---------|
| `connect_timeout_seconds` | Connection timeout | `3` |
| `timeout_seconds` | Request timeout | `5` |
| `retries` | Retry attempts | `1` |
| `retry_sleep_ms` | Delay between retries | `150` |
| `skip_dns_check` | Skip outbound URL DNS validation | `false` |
