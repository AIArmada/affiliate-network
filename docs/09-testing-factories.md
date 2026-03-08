---
title: Testing & Factories
---

# Testing & Factories

Complete guide to testing with the affiliate-network package.

## Model Factories

All models include factories for easy testing.

### AffiliateSiteFactory

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

// Basic site (pending status)
$site = AffiliateSite::factory()->create();

// Verified site
$site = AffiliateSite::factory()->verified()->create();

// Pending with verification token
$site = AffiliateSite::factory()->pending()->create();

// Suspended site
$site = AffiliateSite::factory()->suspended()->create();

// Rejected site
$site = AffiliateSite::factory()->rejected()->create();

// With specific owner
$site = AffiliateSite::factory()->forOwner($merchant)->create();

// With custom settings
$site = AffiliateSite::factory()->withSettings([
    'notifications' => true,
    'auto_approve' => false,
])->create();

// Combined states
$site = AffiliateSite::factory()
    ->verified()
    ->forOwner($merchant)
    ->create();
```

---

### AffiliateOfferFactory

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;

// Active offer with auto-created site
$offer = AffiliateOffer::factory()->create();

// Status variants
$offer = AffiliateOffer::factory()->draft()->create();
$offer = AffiliateOffer::factory()->pending()->create();
$offer = AffiliateOffer::factory()->active()->create();
$offer = AffiliateOffer::factory()->paused()->create();
$offer = AffiliateOffer::factory()->expired()->create();

// Featured offer
$offer = AffiliateOffer::factory()->featured()->create();

// Private (not public) offer
$offer = AffiliateOffer::factory()->private()->create();

// Auto-approve (no application required)
$offer = AffiliateOffer::factory()->autoApprove()->create();

// For specific site
$offer = AffiliateOffer::factory()->forSite($site)->create();

// For specific category
$offer = AffiliateOffer::factory()->forCategory($category)->create();

// Commission types
$offer = AffiliateOffer::factory()->flatRate(500)->create();    // $5.00 fixed
$offer = AffiliateOffer::factory()->percentage(1500)->create(); // 15%

// With date range
$offer = AffiliateOffer::factory()
    ->withDateRange(now(), now()->addMonths(3))
    ->create();

// Complex example
$offer = AffiliateOffer::factory()
    ->forSite($site)
    ->forCategory($category)
    ->active()
    ->featured()
    ->percentage(2000)
    ->withDateRange(now(), now()->addYear())
    ->create();
```

---

### AffiliateOfferCategoryFactory

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;

// Basic category
$category = AffiliateOfferCategory::factory()->create();

// Active/inactive
$category = AffiliateOfferCategory::factory()->active()->create();
$category = AffiliateOfferCategory::factory()->inactive()->create();

// Child category
$parent = AffiliateOfferCategory::factory()->create();
$child = AffiliateOfferCategory::factory()
    ->forParent($parent)
    ->create();

// With owner
$category = AffiliateOfferCategory::factory()
    ->forOwner($merchant)
    ->create();

// With sort order
$category = AffiliateOfferCategory::factory()
    ->sortOrder(5)
    ->create();

// With icon
$category = AffiliateOfferCategory::factory()
    ->withIcon('heroicon-o-shopping-bag')
    ->create();
```

---

### AffiliateOfferCreativeFactory

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;

// Banner creative with dimensions
$creative = AffiliateOfferCreative::factory()
    ->banner(728, 90)
    ->create();

// Text link
$creative = AffiliateOfferCreative::factory()
    ->text()
    ->create();

// Email template
$creative = AffiliateOfferCreative::factory()
    ->email()
    ->create();

// HTML widget
$creative = AffiliateOfferCreative::factory()
    ->html()
    ->create();

// Video
$creative = AffiliateOfferCreative::factory()
    ->video()
    ->create();

// Active/inactive
$creative = AffiliateOfferCreative::factory()->active()->create();
$creative = AffiliateOfferCreative::factory()->inactive()->create();

// For specific offer
$creative = AffiliateOfferCreative::factory()
    ->forOffer($offer)
    ->create();

// With sort order
$creative = AffiliateOfferCreative::factory()
    ->sortOrder(1)
    ->create();
```

---

### AffiliateOfferApplicationFactory

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;

// Pending application
$application = AffiliateOfferApplication::factory()
    ->pending()
    ->create();

// Approved
$application = AffiliateOfferApplication::factory()
    ->approved()
    ->create();

// Rejected with reason
$application = AffiliateOfferApplication::factory()
    ->rejected('Traffic sources not aligned with brand')
    ->create();

// Revoked
$application = AffiliateOfferApplication::factory()
    ->revoked('Terms of service violation')
    ->create();

// For specific offer
$application = AffiliateOfferApplication::factory()
    ->forOffer($offer)
    ->create();

// For specific affiliate
$application = AffiliateOfferApplication::factory()
    ->forAffiliate($affiliate)
    ->create();

// With application reason
$application = AffiliateOfferApplication::factory()
    ->withReason('I have a blog with 100k monthly visitors')
    ->create();

// Complete example
$application = AffiliateOfferApplication::factory()
    ->forOffer($offer)
    ->forAffiliate($affiliate)
    ->approved()
    ->create();
```

---

### AffiliateOfferLinkFactory

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;

// Active link
$link = AffiliateOfferLink::factory()->create();

// Active/inactive
$link = AffiliateOfferLink::factory()->active()->create();
$link = AffiliateOfferLink::factory()->inactive()->create();

// Expired link
$link = AffiliateOfferLink::factory()->expired()->create();

// For specific offer
$link = AffiliateOfferLink::factory()
    ->forOffer($offer)
    ->create();

// For specific affiliate
$link = AffiliateOfferLink::factory()
    ->forAffiliate($affiliate)
    ->create();

// For specific site
$link = AffiliateOfferLink::factory()
    ->forSite($site)
    ->create();

// With sub IDs
$link = AffiliateOfferLink::factory()
    ->withSubIds('campaign-a', 'placement-1', 'creative-001')
    ->create();

// With stats
$link = AffiliateOfferLink::factory()
    ->withStats(
        clicks: 1000,
        conversions: 50,
        revenue: 250000 // $2,500 in cents
    )
    ->create();

// With custom URL parameters
$link = AffiliateOfferLink::factory()
    ->withCustomParams('utm_source=affiliate&utm_medium=banner')
    ->create();

// With expiration date
$link = AffiliateOfferLink::factory()
    ->expiresAt(now()->addMonths(3))
    ->create();

// Complete example
$link = AffiliateOfferLink::factory()
    ->forOffer($offer)
    ->forAffiliate($affiliate)
    ->active()
    ->withSubIds('blog', 'sidebar', 'banner-728x90')
    ->withStats(500, 25, 125000)
    ->expiresAt(now()->addMonths(6))
    ->create();
```

---

## Test Examples

### Testing Site Verification

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

it('generates verification token', function () {
    $site = AffiliateSite::factory()->create();
    $service = app(SiteVerificationService::class);

    $token = $service->generateToken($site);

    expect($token)->toStartWith('affiliatenetwork-verify-');
    expect($site->fresh()->verification_token)->toBe($token);
});

it('marks site as verified on successful verification', function () {
    $site = AffiliateSite::factory()->pending()->create();
    $service = app(SiteVerificationService::class);

    // Mock DNS verification (in real tests, use a mock)
    $site->update(['status' => AffiliateSite::STATUS_VERIFIED, 'verified_at' => now()]);

    expect($site->isVerified())->toBeTrue();
    expect($site->isPending())->toBeFalse();
});
```

### Testing Offer Applications

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;

it('creates application for offer', function () {
    $offer = AffiliateOffer::factory()->active()->create();
    $affiliate = Affiliate::factory()->create();
    $service = app(OfferManagementService::class);

    $application = $service->applyForOffer($offer, $affiliate, 'I want to promote this');

    expect($application->offer_id)->toBe($offer->id);
    expect($application->affiliate_id)->toBe($affiliate->id);
    expect($application->status)->toBe('pending');
    expect($application->reason)->toBe('I want to promote this');
});

it('auto-approves when offer does not require approval', function () {
    $offer = AffiliateOffer::factory()->autoApprove()->create();
    $affiliate = Affiliate::factory()->create();
    $service = app(OfferManagementService::class);

    $application = $service->applyForOffer($offer, $affiliate);

    expect($application->status)->toBe('approved');
});

it('prevents reapplication during cooldown period', function () {
    $offer = AffiliateOffer::factory()->create();
    $affiliate = Affiliate::factory()->create();
    $service = app(OfferManagementService::class);

    // Create rejected application
    $application = $service->applyForOffer($offer, $affiliate);
    $service->rejectApplication($application, 'Not suitable', 'admin');

    // Try to reapply immediately
    expect(fn () => $service->applyForOffer($offer, $affiliate))
        ->toThrow(RuntimeException::class);
});
```

### Testing Link Tracking

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;

it('creates tracking link', function () {
    $offer = AffiliateOffer::factory()->active()->create();
    $affiliate = Affiliate::factory()->create();
    $service = app(OfferLinkService::class);

    $link = $service->createLink($offer, $affiliate, [
        'sub_id' => 'test-campaign',
    ]);

    expect($link->offer_id)->toBe($offer->id);
    expect($link->affiliate_id)->toBe($affiliate->id);
    expect($link->sub_id)->toBe('test-campaign');
    expect($link->code)->toHaveLength(16);
});

it('generates signed tracking URL', function () {
    $link = AffiliateOfferLink::factory()->create();
    $service = app(OfferLinkService::class);

    $url = $service->generateTrackingUrl($link);

    expect($url)->toContain('/affiliate-network/go/');
    expect($url)->toContain($link->code);
});

it('records clicks', function () {
    $link = AffiliateOfferLink::factory()->create(['clicks' => 0]);
    $service = app(OfferLinkService::class);

    $service->recordClick($link);

    expect($link->fresh()->clicks)->toBe(1);
});

it('records conversions with revenue', function () {
    $link = AffiliateOfferLink::factory()->create([
        'conversions' => 0,
        'revenue' => 0,
    ]);
    $service = app(OfferLinkService::class);

    $service->recordConversion($link, 5999);

    $fresh = $link->fresh();
    expect($fresh->conversions)->toBe(1);
    expect($fresh->revenue)->toBe(5999);
});

it('calculates link statistics', function () {
    $link = AffiliateOfferLink::factory()
        ->withStats(1000, 50, 250000)
        ->create();
    $service = app(OfferLinkService::class);

    $stats = $service->getStats($link);

    expect($stats['clicks'])->toBe(1000);
    expect($stats['conversions'])->toBe(50);
    expect($stats['revenue'])->toBe(250000);
    expect($stats['conversion_rate'])->toBe(5.0);
    expect($stats['revenue_per_click'])->toBe(250.0);
});
```

### Testing Multi-Tenancy

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;

it('scopes sites by owner', function () {
    $merchant1 = createMerchant();
    $merchant2 = createMerchant();

    AffiliateSite::factory()->forOwner($merchant1)->count(3)->create();
    AffiliateSite::factory()->forOwner($merchant2)->count(2)->create();

    // Set current owner context
    setCurrentOwner($merchant1);

    expect(AffiliateSite::count())->toBe(3);
});

it('prevents cross-tenant offer creation', function () {
    $merchant1 = createMerchant();
    $merchant2 = createMerchant();

    $site = AffiliateSite::factory()->forOwner($merchant1)->create();

    // Set different owner context
    setCurrentOwner($merchant2);

    expect(fn () => AffiliateOffer::create([
        'site_id' => $site->id,
        'name' => 'Cross-tenant offer',
    ]))->toThrow(RuntimeException::class);
});
```

---

## Testing Tips

### Mocking External Services

For DNS/HTTP verification:

```php
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

it('verifies site via mocked DNS', function () {
    $service = Mockery::mock(SiteVerificationService::class);
    $service->shouldReceive('verify')
        ->once()
        ->andReturn(true);

    app()->instance(SiteVerificationService::class, $service);

    // Test verification
});
```

### Database Transactions

Use database transactions for isolation:

```php
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Or per-test transactions
uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);
```

### Seeding Test Data

```php
beforeEach(function () {
    $this->site = AffiliateSite::factory()->verified()->create();
    $this->affiliate = Affiliate::factory()->create();
    $this->offer = AffiliateOffer::factory()
        ->forSite($this->site)
        ->active()
        ->create();
});
```
