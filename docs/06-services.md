---
title: Services
---

# Services Reference

## SiteVerificationService

Handles domain verification for merchant sites.

### Dependency Injection

```php
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

public function __construct(
    private SiteVerificationService $verificationService
) {}
```

### Methods

#### generateToken

Generate a unique verification token for a site.

```php
$token = $verificationService->generateToken($site);
// Returns: "affiliatenetwork-verify-abc123..."
```

#### verify

Verify a site using the specified method.

```php
$verified = $verificationService->verify($site, 'dns');
// Returns: true if verification successful
```

Verification methods:
- `dns` - DNS TXT record check
- `meta_tag` - HTML meta tag check
- `file` - Well-known file check

#### getInstructions

Get verification instructions for display to users.

```php
$instructions = $verificationService->getInstructions($site, 'dns');
// Returns:
// [
//     'title' => 'DNS TXT Record',
//     'description' => 'Add a TXT record to your domain\'s DNS settings.',
//     'record_type' => 'TXT',
//     'record_name' => '@',
//     'record_value' => 'affiliatenetwork-verify-xxx',
// ]

$instructions = $verificationService->getInstructions($site, 'meta_tag');
// Returns:
// [
//     'title' => 'HTML Meta Tag',
//     'description' => 'Add this meta tag to the <head> section...',
//     'html' => '<meta name="affiliate-network-verify" content="xxx">',
// ]

$instructions = $verificationService->getInstructions($site, 'file');
// Returns:
// [
//     'title' => 'Verification File',
//     'description' => 'Create a file at the following path...',
//     'path' => '/.well-known/affiliate-network-verify.txt',
//     'content' => 'affiliatenetwork-verify-xxx',
// ]
```

---

## OfferManagementService

Manages offers and affiliate applications.

### Dependency Injection

```php
use AIArmada\AffiliateNetwork\Services\OfferManagementService;

public function __construct(
    private OfferManagementService $offerService
) {}
```

### Methods

#### createOffer

Create a new offer for a site.

```php
$offer = $offerService->createOffer($site, [
    'name' => 'Summer Sale',
    'commission_type' => 'percentage',
    'commission_rate' => 1000,
    // ... other fields
]);
```

Auto-generates slug if not provided. Sets status based on `offers.require_approval` config.

#### applyForOffer

Apply for an offer as an affiliate.

```php
$application = $offerService->applyForOffer(
    $offer,
    $affiliate,
    'I have relevant traffic for this offer'
);
```

- Creates new application or returns existing
- Handles cooldown period for rejected applications
- Auto-approves if offer doesn't require approval or config allows

#### approveApplication

Approve a pending application.

```php
$application = $offerService->approveApplication($application, $reviewerId);
```

#### rejectApplication

Reject an application with reason.

```php
$application = $offerService->rejectApplication(
    $application,
    'Traffic sources not aligned with brand guidelines',
    $reviewerId
);
```

#### revokeApplication

Revoke a previously approved application.

```php
$application = $offerService->revokeApplication(
    $application,
    'Terms of service violation',
    $reviewerId
);
```

#### isApprovedForOffer

Check if an affiliate is approved for an offer.

```php
$isApproved = $offerService->isApprovedForOffer($offer, $affiliate);
// Returns: bool
```

#### getApprovedOffers

Get all active offers an affiliate is approved for.

```php
$offers = $offerService->getApprovedOffers($affiliate);
// Returns: Collection<AffiliateOffer>
```

---

## OfferLinkService

Generates and manages tracking links.

### Dependency Injection

```php
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

public function __construct(
    private OfferLinkService $linkService
) {}
```

### Methods

#### createLink

Create a deep link for an affiliate.

```php
$link = $linkService->createLink($offer, $affiliate, [
    'target_url' => 'https://store.com/product/123',
    'sub_id' => 'campaign-a',
    'sub_id_2' => 'placement-1',
    'sub_id_3' => 'creative-banner',
    'custom_parameters' => 'utm_source=affiliate',
    'expires_at' => now()->addMonths(3),
    'metadata' => ['creative_id' => 'banner-001'],
]);
```

#### generateTrackingUrl

Generate a signed tracking URL.

```php
$url = $linkService->generateTrackingUrl($link);
// Returns: https://yoursite.com/affiliate-network/go/abc123?sig=xxx&expires=xxx
```

Uses Laravel's signed URLs with configurable TTL.

#### buildDirectLink

Build a direct link with tracking parameters.

```php
$url = $linkService->buildDirectLink($link);
// Returns: https://store.com/product/123?anl=abc123&sub1=campaign-a
```

#### resolveLink

Resolve a link by its code.

```php
$link = $linkService->resolveLink('abc123');
// Returns: AffiliateOfferLink|null
```

Only returns active, non-expired links.

#### recordClick

Record a click on a link.

```php
$linkService->recordClick($link);
// Increments $link->clicks
```

#### recordConversion

Record a conversion with revenue.

```php
$linkService->recordConversion($link, 5999); // $59.99 in cents
// Increments $link->conversions and adds to $link->revenue
```

#### getStats

Get statistics for a link.

```php
$stats = $linkService->getStats($link);
// Returns:
// [
//     'clicks' => 1250,
//     'conversions' => 45,
//     'revenue' => 267955,
//     'conversion_rate' => 3.6,
//     'revenue_per_click' => 214.36,
// ]
```
