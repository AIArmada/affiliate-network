---
title: Usage
---

# Usage

## Managing Merchant Sites

### Create a Site

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

$site = AffiliateSite::create([
    'name' => 'My Store',
    'domain' => 'mystore.com',
    'description' => 'Online fashion retailer',
    'status' => AffiliateSite::STATUS_PENDING,
]);
```

### Verify a Site

```php
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

$verificationService = app(SiteVerificationService::class);

// Generate verification token
$token = $verificationService->generateToken($site);

// Get verification instructions
$instructions = $verificationService->getInstructions($site, 'dns');
// Returns: ['title' => 'DNS TXT Record', 'record_value' => 'affiliatenetwork-verify-xxx', ...]

// Verify the site
$verified = $verificationService->verify($site, 'dns');

if ($verified) {
    // Site is now STATUS_VERIFIED
}
```

### Verification Methods

| Method | Description |
|--------|-------------|
| `dns` | TXT record on domain |
| `meta_tag` | Meta tag in HTML head |
| `file` | File at `/.well-known/affiliate-network-verify.txt` |

## Managing Offers

### Create an Offer

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;

$offerService = app(OfferManagementService::class);

$offer = $offerService->createOffer($site, [
    'name' => 'Summer Sale Campaign',
    'description' => '20% off summer collection',
    'commission_type' => 'percentage',
    'commission_rate' => 1000, // 10% in basis points
    'cookie_days' => 30,
    'landing_url' => 'https://mystore.com/summer-sale',
    'is_public' => true,
    'requires_approval' => true,
]);
```

### Offer Statuses

| Status | Constant | Description |
|--------|----------|-------------|
| `draft` | `STATUS_DRAFT` | Not published |
| `pending` | `STATUS_PENDING` | Awaiting approval |
| `active` | `STATUS_ACTIVE` | Live and accepting traffic |
| `paused` | `STATUS_PAUSED` | Temporarily disabled |
| `expired` | `STATUS_EXPIRED` | Past end date |
| `rejected` | `STATUS_REJECTED` | Declined by admin |

### Commission Types

```php
// Percentage commission (basis points: 1000 = 10%)
$offer = AffiliateOffer::create([
    'commission_type' => 'percentage',
    'commission_rate' => 1500, // 15%
]);

// Fixed commission (minor units: 1000 = $10.00)
$offer = AffiliateOffer::create([
    'commission_type' => 'fixed',
    'commission_rate' => 500, // $5.00
    'currency' => 'USD',
]);
```

## Affiliate Applications

### Apply for an Offer

```php
use AIArmada\AffiliateNetwork\Services\OfferManagementService;

$offerService = app(OfferManagementService::class);

$application = $offerService->applyForOffer(
    $offer,
    $affiliate,
    'I have a fashion blog with 100k monthly visitors'
);
```

### Approve/Reject Applications

```php
// Approve
$offerService->approveApplication($application, auth()->id());

// Reject with reason
$offerService->rejectApplication(
    $application,
    'Traffic sources not aligned with brand',
    auth()->id()
);

// Revoke previously approved
$offerService->revokeApplication(
    $application,
    'Terms of service violation',
    auth()->id()
);
```

### Check Application Status

```php
$isApproved = $offerService->isApprovedForOffer($offer, $affiliate);

// Get all approved offers for an affiliate
$approvedOffers = $offerService->getApprovedOffers($affiliate);
```

## Tracking Links

### Generate a Tracking Link

```php
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

$linkService = app(OfferLinkService::class);

$link = $linkService->createLink($offer, $affiliate, [
    'target_url' => 'https://mystore.com/product/123',
    'sub_id' => 'blog-post-summer',
    'sub_id_2' => 'sidebar-banner',
]);

// Get tracking URL (signed, expires in 30 days)
$trackingUrl = $linkService->generateTrackingUrl($link);
// https://yoursite.com/affiliate-network/go/abc123?sig=xxx

// Or build direct link with parameters
$directUrl = $linkService->buildDirectLink($link);
// https://mystore.com/product/123?anl=abc123&sub1=blog-post-summer
```

### Track Clicks and Conversions

```php
// Record a click
$linkService->recordClick($link);

// Record a conversion with revenue
$linkService->recordConversion($link, 5999); // $59.99 in cents

// Get link statistics
$stats = $linkService->getStats($link);
// [
//     'clicks' => 1250,
//     'conversions' => 45,
//     'revenue' => 267955, // cents
//     'conversion_rate' => 3.6,
//     'revenue_per_click' => 214.36,
// ]
```

### Tracking Model Notes

`OfferLinkService::recordConversion()` updates the package's own `AffiliateOfferLink` counters and revenue totals.

It does not create or mutate core `aiarmada/affiliates` `AffiliateConversion` rows, so the newer core affiliates fields like `external_reference`, `value_minor`, and subject metadata are not required here.

## Categories

### Create Categories

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;

$parentCategory = AffiliateOfferCategory::create([
    'name' => 'Fashion',
    'slug' => 'fashion',
    'icon' => 'shirt',
    'sort_order' => 1,
    'is_active' => true,
]);

$childCategory = AffiliateOfferCategory::create([
    'parent_id' => $parentCategory->id,
    'name' => 'Women\'s Clothing',
    'slug' => 'womens-clothing',
    'sort_order' => 1,
    'is_active' => true,
]);
```

### Query Categories

```php
// Get root categories
$rootCategories = AffiliateOfferCategory::whereNull('parent_id')
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->get();

// Get with children
$categories = AffiliateOfferCategory::with('children')
    ->whereNull('parent_id')
    ->get();
```

## Creative Assets

### Add Creatives to Offers

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;

$banner = AffiliateOfferCreative::create([
    'offer_id' => $offer->id,
    'type' => AffiliateOfferCreative::TYPE_BANNER,
    'name' => '728x90 Leaderboard',
    'url' => 'https://cdn.mystore.com/banners/summer-728x90.jpg',
    'width' => 728,
    'height' => 90,
    'alt_text' => 'Summer Sale - 20% Off',
    'is_active' => true,
    'sort_order' => 1,
]);

$textLink = AffiliateOfferCreative::create([
    'offer_id' => $offer->id,
    'type' => AffiliateOfferCreative::TYPE_TEXT,
    'name' => 'Summer Sale Text Link',
    'html_code' => '<a href="{tracking_url}">Shop Summer Sale - 20% Off!</a>',
    'is_active' => true,
    'sort_order' => 2,
]);
```

### Creative Types

| Type | Constant | Use Case |
|------|----------|----------|
| `banner` | `TYPE_BANNER` | Image banners |
| `text` | `TYPE_TEXT` | Text links |
| `email` | `TYPE_EMAIL` | Email templates |
| `html` | `TYPE_HTML` | HTML widgets |
| `video` | `TYPE_VIDEO` | Video content |

## Checkout Integration

The `affiliate-network` package provides seamless integration with the `checkout` package for internal sites that are part of your network. This allows you to track affiliate referrals and record conversions automatically when an order is placed through the checkout package.

### Enable Integration

To enable the integration, set the following environment variable or update your config:

```php
// .env
AFFILIATE_NETWORK_CHECKOUT_ENABLED=true

When a network-attributed order converts, the listener stores network attribution details under `order.metadata.network_attribution` and increments the related `AffiliateOfferLink` conversion metrics.
```

### How it Works

1. **Tracking**: When a user visits your site with a network link parameter (default: `anl`), the `TrackNetworkLinkCookie` middleware captures the link identifier and stores it in a secure cookie.
2. **Attribution**: The cookie persists based on the configured lifetime (default: 30 days).
3. **Conversion**: When an order is completed via the `checkout` package, it triggers a `CommissionAttributionRequired` event.
4. **Recording**: The `RecordNetworkConversionForOrder` listener catches this event, reads the attribution cookie, and records a conversion for the respective affiliate offer through the `OfferLinkService`.

### Configuration Options

You can customize the integration behavior in `config/affiliate-network.php`:

```php
'checkout' => [
    'enabled' => env('AFFILIATE_NETWORK_CHECKOUT_ENABLED', false),
    'middleware_group' => env('AFFILIATE_NETWORK_MIDDLEWARE_GROUP', 'web'),
    'listen_for_orders' => env('AFFILIATE_NETWORK_LISTEN_ORDERS', true),
],
```

The conversion recording automatically captures:
- Order total (translated to commission)
- Currency
- Link reference
- Sub IDs (from the original tracking link)
- Order ID (stored in conversion metadata for audit)
