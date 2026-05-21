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
        'json_column_type' => env('AFFILIATE_NETWORK_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
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
        'default_ttl_minutes' => env('AFFILIATE_NETWORK_LINK_TTL', 60 * 24 * 30),
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
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'connect_timeout_seconds' => 3,
        'timeout_seconds' => 5,
        'retries' => 1,
        'retry_sleep_ms' => 150,
        'skip_dns_check' => false,
    ],

];
