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
$link->isExpired(): bool                    // Check if the backing link is expired
$link->link(): BelongsTo                    // Backing tracked Link
$link->offer(): BelongsTo                   // Parent AffiliateOffer
$link->affiliate(): BelongsTo               // Associated Affiliate
$link->site(): BelongsTo                    // Optional AffiliateSite
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
    string $affiliateId,
    ?string $reason = null
): AffiliateOfferApplication;

// Approve/Reject/Revoke
$service->approveApplication(AffiliateOfferApplication $app, ?string $reviewedBy): AffiliateOfferApplication;
$service->rejectApplication(AffiliateOfferApplication $app, string $reason, ?string $reviewedBy): AffiliateOfferApplication;
$service->revokeApplication(AffiliateOfferApplication $app, string $reason, ?string $reviewedBy): AffiliateOfferApplication;

// Check approval status
$isApproved = $service->isApprovedForOffer(AffiliateOffer $offer, string $affiliateId): bool;

// Get approved offers (approved network applications plus published local
// imports with an approved core program membership)
$offers = $service->getApprovedOffers(string $affiliateId, int $limit = 500): Collection;

// Batch per-offer statuses in a fixed handful of queries
$map = $service->applicationStatusMap(string $affiliateId, Collection $offers): array;
```

### OfferLinkService

```php
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

$service = app(OfferLinkService::class);

// Create link
$link = $service->createLink(
    AffiliateOffer $offer,
    string $affiliateId,
    array $options = []
): AffiliateOfferLink;

// Options: target_url, sub_id, sub_id_2, sub_id_3, custom_parameters, expires_at, metadata

// Throws unless the offer is active (published + within its window) and,
// when the offer requires approval, the affiliate is approved. target_url
// must be an http(s) URL. The link inherits the offer currency.

// Note: metadata is the extension point if your application wants to carry
// subject-specific context that may later be bridged into core affiliates flows.

// Generate URL
$signedUrl = $service->generateTrackingUrl(AffiliateOfferLink $link): string;

// Resolve link
$link = $service->resolveLink(string $slug): ?AffiliateOfferLink;

// Track events
$service->recordConversion(AffiliateOfferLink $link, int $revenueMinor = 0, ?string $currency = null): void;
// On a currency mismatch the conversion is counted but revenue is skipped (and logged).

// Get statistics
$stats = $service->getStats(AffiliateOfferLink $link): array;
// Returns: clicks, conversions, revenue, currency, formatted_revenue, conversion_rate, revenue_per_click
```

### OfferImportService

```php
use AIArmada\AffiliateNetwork\Services\OfferImportService;

$service = app(OfferImportService::class);

// Sync one program (local shared-DB when the site has no catalog_url,
// remote HTTP pull otherwise)
$result = $service->sync($site, $programId);
// Returns: created, updated, skipped, locked, failed

// Sync every available program; one bad program never aborts the rest
$result = $service->syncAll($site);
// Returns: programs, created, updated, skipped, locked, failed
```

Upserts by `(site_id, external_program_id, subject_key)` with checksum
skips. Imported offers land as `draft` with `rate_source = synced`.
Operator rate edits flip the lock to `manual`; later syncs hold rates back
(`locked`) until the operator flips it back. Artisan:

```bash
php artisan affiliate-network:sync-offers {site} [--program={id}]
```

---

## Routes

Redirects are served by `aiarmada/links` (`GET /go/{slug}`, signed URLs).
The network binds an `OfferLinkGate` that blocks the redirect with `410`
when the link is inactive, the offer is inactive, no site is verified, or a
required approval is missing; unknown slugs return `404`. Clicks increment
the link counter through the `IncrementNetworkLinkClicks` listener on
`LinkClicked`.

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
// From ScopesByBelongsToOwner via site
"Cannot create record for a site owned by a different owner."

// From ScopesByBelongsToOwner via affiliate
"Cannot create record for an affiliate owned by a different owner."
```

### Reapplication Cooldown

```php
// From OfferManagementService::applyForOffer
"Cannot reapply for this offer yet. Please wait {$cooldownDays} days after rejection."
```
