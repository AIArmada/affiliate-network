---
title: API Reference
---

# API Reference

Complete reference for all public classes and methods.

## Models

### AffiliateSite

Represents a merchant's website/domain in the network.

#### Constants

```php
AffiliateSite::STATUS_PENDING    // 'pending'
AffiliateSite::STATUS_VERIFIED   // 'verified'
AffiliateSite::STATUS_SUSPENDED  // 'suspended'
AffiliateSite::STATUS_REJECTED   // 'rejected'
```

#### Methods

```php
$site->isVerified(): bool   // status === 'verified' && verified_at !== null
$site->isPending(): bool    // status === 'pending'
$site->offers(): HasMany    // Related AffiliateOffer models
$site->owner(): MorphTo     // Owner relationship (multi-tenancy)
```

---

### AffiliateOffer

Represents an affiliate offer/campaign.

#### Constants

```php
AffiliateOffer::STATUS_DRAFT     // 'draft'
AffiliateOffer::STATUS_PENDING   // 'pending'
AffiliateOffer::STATUS_ACTIVE    // 'active'
AffiliateOffer::STATUS_PAUSED    // 'paused'
AffiliateOffer::STATUS_EXPIRED   // 'expired'
AffiliateOffer::STATUS_REJECTED  // 'rejected'
```

#### Methods

```php
$offer->isActive(): bool        // Checks status AND date range
$offer->site(): BelongsTo       // Parent AffiliateSite
$offer->category(): BelongsTo   // Optional AffiliateOfferCategory
$offer->creatives(): HasMany    // AffiliateOfferCreative models
$offer->applications(): HasMany // AffiliateOfferApplication models
$offer->links(): HasMany        // AffiliateOfferLink models
```

---

### AffiliateOfferCategory

Hierarchical category for organizing offers.

#### Methods

```php
$category->parent(): BelongsTo    // Parent category (nullable)
$category->children(): HasMany    // Child categories
$category->offers(): HasMany      // Offers in this category
$category->owner(): MorphTo       // Owner relationship
```

---

### AffiliateOfferCreative

Promotional asset (banner, text, HTML, etc.).

#### Constants

```php
AffiliateOfferCreative::TYPE_BANNER  // 'banner'
AffiliateOfferCreative::TYPE_TEXT    // 'text'
AffiliateOfferCreative::TYPE_EMAIL   // 'email'
AffiliateOfferCreative::TYPE_HTML    // 'html'
AffiliateOfferCreative::TYPE_VIDEO   // 'video'
```

#### Methods

```php
$creative->offer(): BelongsTo  // Parent AffiliateOffer
```

---

### AffiliateOfferApplication

Affiliate's application to promote an offer.

#### Constants

```php
AffiliateOfferApplication::STATUS_PENDING   // 'pending'
AffiliateOfferApplication::STATUS_APPROVED  // 'approved'
AffiliateOfferApplication::STATUS_REJECTED  // 'rejected'
AffiliateOfferApplication::STATUS_REVOKED   // 'revoked'
```

#### Methods

```php
$application->isPending(): bool    // status === 'pending'
$application->isApproved(): bool   // status === 'approved'
$application->offer(): BelongsTo   // Parent AffiliateOffer
$application->affiliate(): BelongsTo // Associated Affiliate
```

---

### AffiliateOfferLink

Tracking link for affiliate promotions.

#### Methods

```php
$link->incrementClicks(): void              // Increment click counter
$link->recordConversion(int $revenue): void // Record conversion with revenue
$link->isExpired(): bool                    // Check if expires_at is past
$link->offer(): BelongsTo                   // Parent AffiliateOffer
$link->affiliate(): BelongsTo               // Associated Affiliate
$link->site(): BelongsTo                    // Optional AffiliateSite

AffiliateOfferLink::generateCode(): string  // Generate 16-char hex code
```

---

## Services

### SiteVerificationService

```php
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

$service = app(SiteVerificationService::class);

// Generate verification token
$token = $service->generateToken(AffiliateSite $site): string;

// Verify site (dns|meta_tag|file)
$verified = $service->verify(AffiliateSite $site, string $method): bool;

// Get instructions for display
$instructions = $service->getInstructions(AffiliateSite $site, string $method): array;
```

### OfferManagementService

```php
use AIArmada\AffiliateNetwork\Services\OfferManagementService;

$service = app(OfferManagementService::class);

// Create offer
$offer = $service->createOffer(AffiliateSite $site, array $data): AffiliateOffer;

// Apply for offer
$application = $service->applyForOffer(
    AffiliateOffer $offer,
    Affiliate $affiliate,
    ?string $reason = null
): AffiliateOfferApplication;

// Approve/Reject/Revoke
$service->approveApplication(AffiliateOfferApplication $app, ?string $reviewedBy): AffiliateOfferApplication;
$service->rejectApplication(AffiliateOfferApplication $app, string $reason, ?string $reviewedBy): AffiliateOfferApplication;
$service->revokeApplication(AffiliateOfferApplication $app, string $reason, ?string $reviewedBy): AffiliateOfferApplication;

// Check approval status
$isApproved = $service->isApprovedForOffer(AffiliateOffer $offer, Affiliate $affiliate): bool;

// Get approved offers
$offers = $service->getApprovedOffers(Affiliate $affiliate): Collection;
```

### OfferLinkService

```php
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

$service = app(OfferLinkService::class);

// Create link
$link = $service->createLink(
    AffiliateOffer $offer,
    Affiliate $affiliate,
    array $options = []
): AffiliateOfferLink;

// Options: target_url, sub_id, sub_id_2, sub_id_3, custom_parameters, expires_at, metadata

// Generate URLs
$signedUrl = $service->generateTrackingUrl(AffiliateOfferLink $link): string;
$directUrl = $service->buildDirectLink(AffiliateOfferLink $link): string;

// Resolve link
$link = $service->resolveLink(string $code): ?AffiliateOfferLink;

// Track events
$service->recordClick(AffiliateOfferLink $link): void;
$service->recordConversion(AffiliateOfferLink $link, int $revenueMinor = 0): void;

// Get statistics
$stats = $service->getStats(AffiliateOfferLink $link): array;
// Returns: clicks, conversions, revenue, conversion_rate, revenue_per_click
```

---

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/affiliate-network/go/{code}` | `affiliate-network.redirect` | `LinkRedirectController` |

The redirect controller:

1. Resolves link by code
2. Validates link is active and not expired
3. Validates offer is active
4. Records click
5. Redirects to target URL with tracking parameters

### Controller Logic

```php
final class LinkRedirectController
{
    public function __invoke(Request $request, string $code, OfferLinkService $linkService): RedirectResponse
    {
        $link = $linkService->resolveLink($code);

        if ($link === null) {
            abort(404, 'Link not found');
        }

        if ($link->isExpired()) {
            abort(410, 'Link has expired');
        }

        if (! $link->offer->isActive()) {
            abort(410, 'Offer is no longer active');
        }

        $linkService->recordClick($link);

        $redirectUrl = $linkService->buildDirectLink($link);

        return redirect()->away($redirectUrl);
    }
}
```

---

## Events

The package does not emit custom events by default. Use Laravel model events for observing:

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;

AffiliateOfferApplication::created(function ($application) {
    // Notify merchant of new application
});

AffiliateOfferApplication::updated(function ($application) {
    if ($application->wasChanged('status')) {
        // Notify affiliate of status change
    }
});
```

---

## Exceptions

### RuntimeException

Thrown by scoping traits for cross-tenant violations:

```php
// From ScopesBySiteOwner
"Cannot create record for a site owned by a different owner."

// From ScopesByAffiliateOwner
"Cannot create record for an affiliate owned by a different owner."
```

### Reapplication Cooldown

```php
// From OfferManagementService::applyForOffer
"Cannot reapply for this offer yet. Please wait {$cooldownDays} days after rejection."
```
