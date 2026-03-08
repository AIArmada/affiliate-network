<?php

declare(strict_types=1);

$tablePrefix = env('AFFILIATE_NETWORK_TABLE_PREFIX', 'affiliate_network_');
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
        'signing_key' => env('AFFILIATE_NETWORK_LINK_SIGNING_KEY', env('AFFILIATES_LINK_SIGNING_KEY', env('APP_KEY'))),
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
        'name' => env('AFFILIATE_NETWORK_COOKIE_NAME', 'affiliate_network_link'),
        'lifetime_minutes' => env('AFFILIATE_NETWORK_COOKIE_LIFETIME', 60 * 24 * 30),
        'secure' => env('AFFILIATE_NETWORK_COOKIE_SECURE', true),
        'same_site' => env('AFFILIATE_NETWORK_COOKIE_SAMESITE', 'lax'),
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
