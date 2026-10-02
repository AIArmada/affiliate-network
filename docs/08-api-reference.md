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

Represents an affiliate offer/campaign. `status` is the
`AIArmada\AffiliateNetwork\Enums\OfferStatus` enum.

```php
use AIArmada\AffiliateNetwork\Enums\OfferStatus;

OfferStatus::Draft;     // 'draft'
OfferStatus::Published; // 'published'
OfferStatus::Archived;  // 'archived'
```

> **warning:**
> `AffiliateOffer` declares no `STATUS_*` constants, and there is no `pending`,
> `active`, `paused`, `expired`, or `rejected` status. The Filament `activate`
> action publishes (`Published`, stamps `published_at`); `pause` archives
> (`Archived`, stamps `archived_at`).

#### Methods

```php
$offer->isDraft(): bool           // status === Draft
$offer->isActive(): bool          // status === Published AND within starts_at/ends_at
$offer->isFixed(): bool           // rate_fixed_minor path
$offer->formattedRate(): string
$offer->site(): BelongsTo       // Parent AffiliateSite
$offer->category(): BelongsTo   // Optional AffiliateOfferCategory
$offer->creatives(): HasMany    // AffiliateOfferCreative models
$offer->applications(): HasMany // AffiliateOfferApplication models
$offer->links(): HasMany        // AffiliateOfferLink models
$offer->legs(): HasMany         // NetworkConversionLeg models
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
AffiliateOfferCreative::TYPE_IMAGE   // 'image'
AffiliateOfferCreative::TYPE_DOCUMENT // 'document'
```

#### Methods

```php
$creative->offer(): BelongsTo  // Parent AffiliateOffer
```

---

### AffiliateOfferApplication

Affiliate's application to promote an offer. `status` is the
`AIArmada\AffiliateNetwork\Enums\ApplicationStatus` enum.

```php
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;

ApplicationStatus::Pending;   // 'pending'
ApplicationStatus::Approved;  // 'approved'
ApplicationStatus::Rejected;  // 'rejected'
ApplicationStatus::Revoked;   // 'revoked'
```

> **warning:**
> `AffiliateOfferApplication` declares no `STATUS_*` constants. Read the enum.

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

// Get approved offers (approved network applications)
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
$trackingUrl = $service->generateTrackingUrl(AffiliateOfferLink $link): string;

// Resolve link
$link = $service->resolveLink(string $slug): ?AffiliateOfferLink;

// Track events
$leg = $service->recordConversion(
    AffiliateOfferLink $link,
    int $revenueMinor = 0,
    ?string $currency = null,
    ?string $externalReference = null,
    LegStatus $status = LegStatus::Posted,
): ?NetworkConversionLeg;
// Returns null when no external reference is supplied. On a currency mismatch
// the conversion is counted but revenue is skipped (and logged).

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
skips. Imported offers land as `draft` with `source = mirrored`.
Operator rate edits flip the lock to `manual`; later syncs hold rates back
(`locked`) until the operator flips it back. Artisan:

```bash
php artisan affiliate-network:sync-offers {site} [--program={id}]
```

---

## Routes

Redirects are served by `aiarmada/links` (`GET /go/{slug}`, unsigned public URLs).
The network binds an `OfferLinkGate` that blocks the redirect with `410`
when the link is inactive, the offer is inactive, no site is verified, or a
required approval is missing; unknown slugs return `404`. Clicks increment
the link counter through the `IncrementNetworkLinkClicks` listener on
`LinkClicked`.

---

## Events

The package ships five events in `AIArmada\AffiliateNetwork\Events`, dispatched
by the corresponding Action: `OfferCreated`, `OfferUpdated`,
`ApplicationSubmitted`, `ApplicationApproved`, `NetworkConversionRecorded`.

Model events on `AffiliateOfferApplication` are also available for finer hooks:

```php
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Events\OfferUpdated;

OfferCreated::class;             // (AffiliateOffer $offer)
OfferUpdated::class;             // (AffiliateOffer $offer)
ApplicationSubmitted::class;     // (AffiliateOfferApplication $application)
ApplicationApproved::class;      // (AffiliateOfferApplication $application)
NetworkConversionRecorded::class; // (AffiliateOfferLink $link, int $revenueMinor, ?string $currency, ?NetworkConversionLeg $leg)
```


### Approval event contract

Listen to `ApplicationApproved` for approved-side effects. Both manual approval
and automatic approval through package actions emit it with the approved
application. Automatic approval emits `ApplicationSubmitted` first.
`ApplicationApproved` waits for the enclosing database transaction to commit and
is discarded on rollback. Pending creation, cooldown reapplication, returning an
existing application, and repeating approval on an already-approved application
emit no approval event. Direct model updates do not emit this domain event.

The built-in approval notification listener also runs for automatic approvals.
Default tracking-link provisioning remains host policy. Hosts should make their
side effects idempotent; this event does not guarantee exactly-once delivery.

> **warning:**
> This changes the approval event contract. Hosts should handle approval through
> `ApplicationApproved` and remove approval side effects from
> `ApplicationSubmitted` listeners to avoid running them twice. Already-approved
> records are not replayed or backfilled. No schema change is required.

---

## Exceptions

### Cross-tenant owner violations

Thrown by `ScopesByBelongsToOwner` on create/update when the owner relation is
missing or owned by someone else. The message is generic and interpolates the
model class:

```php
// From ScopesByBelongsToOwner (site, offer.site, and affiliate paths alike)
"Cannot create or update {ModelClass} for an inaccessible or missing owner relation."

// When ownerViaRelation() does not start with a belongs-to relation
"{ModelClass}::ownerViaRelation() must begin with a belongs-to relation."
```

### Reapplication Cooldown

`ApplyToOffer` throws
`AIArmada\AffiliateNetwork\Exceptions\ApplicationAlreadySubmittedException`
(a `RuntimeException`) while a rejected application is still inside
`affiliate-network.applications.cooldown_days`.

## Mirrored and manual creatives

`AffiliateOfferCreative::external_creative_id` identifies merchant-origin
rows; null identifies manual rows. Imported `source_asset_url` stores the public merchant
asset URL. Manual assets use only Media Library's single-file `creative_asset`
collection on the `public` disk; `getAssetUrl()` resolves the relevant asset
and returns null for file-less creatives. `destination_url` is the click target.

Catalog snapshots must use version `v2` and include `creatives` (even when
empty). `text_link` maps to `text`; image/document/video/banner/email types
retain their meaning. Merchant type and tracking code are retained as
`metadata.merchant_type` and `metadata.merchant_tracking_code`; merchant
personalized HTML is never imported.

The importer matches `(offer_id, external_creative_id)`, preserves `is_active`
and `sort_order`, and deletes absent imported rows while keeping manual rows.
A separate `creatives_checksum` propagates creative-only changes without
rewriting rates. Each subject's offer and creative reconciliation is atomic;
failures count toward `failed` and mark the site `partial`.

Hosts can use these services from their storefront or API. Issue a creative
link once and retain its ID; rendering existing links does not need new links.

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

$creative = AffiliateOfferCreative::query()->findOrFail($creativeId);
$service = app(OfferLinkService::class);
$link = $service->createCreativeLink($creative, $affiliateId);
$payload = $service->creativePayload($creative, $link);
// id, type, name, description, asset_url, tracking_url, embed_code, width, height
```

`createCreativeLink()` requires an active creative and retains the normal
verified-site, published-offer, and approval checks. It uses the creative's
destination, falling back to the offer landing URL and then the site homepage.
`creativePayload()` requires a link issued for that creative. Its tracking URL
is the public network `/go/{slug}` URL; the backing link adds network
attribution at redirect. Banner/image embeds require an asset. Manual HTML
and email templates may use `{{tracking_url}}`; other types produce a text
link. Treat manual template HTML as trusted operator-authored content.

> **warning**
> Breaking catalog contract: deploy merchant and network version 2 together.
> Existing migrations define `external_creative_id`, `destination_url`, and
> `creatives_checksum`; there are no compatibility paths or backfills.
> Merchant files must remain publicly reachable because imports store URLs,
> not copies of file bytes.
