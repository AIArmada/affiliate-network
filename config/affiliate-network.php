<?php

declare(strict_types=1);

$tablePrefix = 'affiliate_network_';
$tables = [
    'sites' => $tablePrefix . 'sites',
    'offers' => $tablePrefix . 'offers',
    'offer_categories' => $tablePrefix . 'offer_categories',
    'offer_creatives' => $tablePrefix . 'offer_creatives',
    'offer_applications' => $tablePrefix . 'offer_applications',
    'offer_links' => $tablePrefix . 'offer_links',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'tables' => $tables,
        'json_column_type' => env('AFFILIATE_NETWORK_JSON_COLUMN_TYPE', 'jsonb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Affiliate Model
    |--------------------------------------------------------------------------
    |
    | Eloquent model behind the affiliate() relations on applications and
    | links. The network never assumes one: aiarmada/affiliates binds its
    | Affiliate model here, or point it at your own implementation.
    |
    */
    'models' => [
        'affiliate' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | When enabled, sites/offers are automatically scoped to the current owner.
    | This allows network operators to manage multiple merchant tenants.
    |
    */
    'owner' => [
        'enabled' => env('AFFILIATE_NETWORK_OWNER_ENABLED', false),
        'include_global' => env('AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Fallback ISO currency for reporting totals when network revenue spans
    | multiple currencies and for records without an explicit currency.
    |
    */
    'currency' => [
        'default' => env('AFFILIATE_NETWORK_DEFAULT_CURRENCY', 'MYR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Fees
    |--------------------------------------------------------------------------
    |
    | Take-rate in basis points on every network commission, unless the
    | offer overrides it with network_fee_bp. Zero until the business
    | sets a rate — plumbing ships rate-agnostic.
    |
    */
    'fees' => [
        'default_bp' => env('AFFILIATE_NETWORK_FEE_BP', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Managed notifications via aiarmada/communications when installed
    | (applications, conversions). On by default; silent when the
    | communications package is absent regardless of this flag.
    |
    */
    'notifications' => [
        'enabled' => env('AFFILIATE_NETWORK_NOTIFICATIONS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Offers
    |--------------------------------------------------------------------------
    */
    'offers' => [
        'require_approval' => env('AFFILIATE_NETWORK_OFFERS_REQUIRE_APPROVAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Applications
    |--------------------------------------------------------------------------
    */
    'applications' => [
        'auto_approve' => env('AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE', false),
        'cooldown_days' => env('AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep Links
    |--------------------------------------------------------------------------
    */
    'links' => [
        'parameter' => env('AFFILIATE_NETWORK_LINK_PARAM', 'anl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookies
    |--------------------------------------------------------------------------
    |
    | Cookie settings for tracking affiliate referrals on internal sites.
    | Only applies when checkout integration is enabled.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Checkout Integration
    |--------------------------------------------------------------------------
    |
    | Enable this when the site uses the commerce checkout package and needs
    | to track conversions from network affiliate links (Scenario B).
    |
    */
    'checkout' => [
        'enabled' => env('AFFILIATE_NETWORK_CHECKOUT_ENABLED', false),
        'middleware_group' => env('AFFILIATE_NETWORK_MIDDLEWARE_GROUP', 'web'),
        'listen_for_orders' => env('AFFILIATE_NETWORK_LISTEN_ORDERS', true),
        'attribution_window_hours' => env('AFFILIATE_NETWORK_ATTRIBUTION_WINDOW_HOURS', 720),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog Sync (merchant program mirror)
    |--------------------------------------------------------------------------
    |
    | Local shared-DB reads when a site has no catalog_url; remote HTTP pull
    | when the site owner configured catalog_url + token. max_subjects caps
    | imported subjects per program sync (agreed default: 500);
    | max_programs caps programs per syncAll run (agreed default: 100).
    |
    */
    'sync' => [
        'enabled' => env('AFFILIATE_NETWORK_SYNC_ENABLED', true),
        'max_subjects' => env('AFFILIATE_NETWORK_SYNC_MAX_SUBJECTS', 500),
        'max_programs' => env('AFFILIATE_NETWORK_SYNC_MAX_PROGRAMS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant Postbacks
    |--------------------------------------------------------------------------
    |
    | Remote merchants report paid orders here so the network can record
    | conversions without sharing a database. Merchants authenticate with
    | the same catalog token the network uses to pull their catalog
    | (stored encrypted on the site). Disabled by default.
    |
    */

    'postbacks' => [
        'enabled' => env('AFFILIATE_NETWORK_POSTBACKS_ENABLED', false),
        'prefix' => env('AFFILIATE_NETWORK_POSTBACKS_PREFIX', 'api/affiliate-network'),
        'middleware' => ['api', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'connect_timeout_seconds' => 3,
        'timeout_seconds' => 5,
        'retries' => 1,
        'retry_sleep_ms' => 150,
        'max_response_bytes' => 1024 * 1024,
    ],

];
