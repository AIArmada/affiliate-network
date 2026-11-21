---
title: Services
---

# Services Reference

## Canonical API: Actions

The canonical orchestration surface for affiliate-network is the `Actions` tree. Prefer these over direct service calls:

| Action | Purpose |
|--------|---------|
| `app(RegisterSite::class)->execute($owner, $data)` | Create a merchant site |
| `app(CreateSite::class)->execute($data)` | Create a site from an operator form |
| `app(UpdateSite::class)->execute($site, $data)` | Update site details and catalog token |
| `app(VerifySite::class)->execute($site)` | Pending site -> verified |
| `app(RejectSite::class)->execute($site)` | Pending site -> rejected |
| `app(SuspendSite::class)->execute($site)` | Verified site -> suspended |
| `app(ReinstateSite::class)->execute($site)` | Suspended site -> verified |
| `app(CreateOffer::class)->execute($site, $data)` | Create a new offer |
| `app(UpdateOffer::class)->execute($offer, $data)` | Update an existing offer |
| `app(PublishOffer::class)->execute($offer)` | Publish an offer and stamp `published_at` |
| `app(ArchiveOffer::class)->execute($offer)` | Archive an offer and stamp `archived_at` |
| `app(SubmitOffer::class)->execute($site, $data)` | Merchant-submitted offer (always `draft`) |
| `app(ApplyToOffer::class)->execute($offer, $affiliateId, $reason)` | Apply to an offer |
| `app(ApproveApplication::class)->execute($application, $reviewedBy)` | Approve a pending application |
| `app(RejectApplication::class)->execute($application, $reason, $reviewedBy)` | Reject a pending application |
| `app(RevokeApplication::class)->execute($application, $reason, $reviewedBy)` | Revoke an approved application |
| `app(CreateCreative::class)->execute($offer, $data)` | Create a manual creative |
| `app(UpdateCreative::class)->execute($creative, $data)` | Update a creative (imported rows accept operator fields only) |
| `app(DeleteCreative::class)->execute($creative)` | Delete a manual creative |
| `app(SyncSiteOffers::class)->execute($site, $programId)` | Operator catalog sync (privileged owner-context entry) |
| `app(SyncOfferCreatives::class)->execute($offer, $creatives)` | Reconcile imported creatives |
| `app(RecordNetworkConversion::class)->execute($link, $revenueMinor, $currency, $externalReference, $status)` | Record a conversion (posts a leg when `$externalReference` is set) |

`ApplyToOffer` takes an affiliate **id string**, not a model, and the third
argument is `$reason`. None of these actions use `AsAction` — call them through
the container with `execute()`.

Offer and application actions dispatch events (`OfferCreated`, `OfferUpdated`,
`ApplicationSubmitted`, `ApplicationApproved`, `NetworkConversionRecorded`).
Site lifecycle and creative actions do not emit domain events.

> **warning**
> Breaking change: `AffiliateIdentityResolver` now declares
> `requiresMerchantProfile()` and `hasMerchantProfile()`. Custom identity
> adapters must implement both. Return `false` from `requiresMerchantProfile()`
> for user-key identity and delegate `hasMerchantProfile()` to your merchant
> lookup. The profile guard no longer checks for a concrete adapter class.

---

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

## Site lifecycle

Operator transitions live in the core package as explicit actions and validate
the current status before writing. A suspended site retains its verification
timestamp for reinstatement.

```php
use AIArmada\AffiliateNetwork\Actions\RejectSite;
use AIArmada\AffiliateNetwork\Actions\ReinstateSite;
use AIArmada\AffiliateNetwork\Actions\SuspendSite;
use AIArmada\AffiliateNetwork\Actions\VerifySite;

app(VerifySite::class)->execute($site);     // pending -> verified
app(SuspendSite::class)->execute($site);    // verified -> suspended
app(ReinstateSite::class)->execute($site);  // suspended -> verified

app(RejectSite::class)->execute($anotherPendingSite);
```

`CreateSite` and `UpdateSite` own operator form writes, including domain
normalization, the `verified_at` invariant, and catalog-token encryption.
`AffiliateSite::matchesCatalogToken()` is the single constant-time token check
used by both merchant postback endpoints.

Admin and console surfaces are responsible for entering the site owner context
before calling these actions; the actions themselves stay inside the ambient
owner scope and never widen it.

---

## Creatives

Manual creatives are written through core actions, never through the Filament
adapter:

```php
use AIArmada\AffiliateNetwork\Actions\CreateCreative;
use AIArmada\AffiliateNetwork\Actions\DeleteCreative;
use AIArmada\AffiliateNetwork\Actions\UpdateCreative;

$creative = app(CreateCreative::class)->execute($offer, $data);
app(UpdateCreative::class)->execute($creative, $data);
app(DeleteCreative::class)->execute($creative);
```

`CreateCreative` always clears `external_creative_id` and `source_asset_url`, so
a caller cannot forge merchant provenance. Imported creatives accept only
`is_active` and `sort_order` on update and cannot be deleted; catalog sync owns
them through `SyncOfferCreatives`.

---

## OfferManagementService

Manages offers and affiliate applications.

This is the discovery-side API. Every offer — local import or remote
mirror — enrolls through the network application flow, which never touches
merchant program memberships. When the Affiliates identity adapter is bound
and either `affiliate-network.owner.enabled` or
`affiliates.owner.enabled` is enabled, the actor must have a merchant
`Affiliate` row before applying or creating links.
Standalone installs and combined installs with both owner settings disabled
retain user-key fallback writes. Commission writes land as network legs
(`NetworkBooks`); the merchant ledger only receives postings through the
fulfillment step when `aiarmada/affiliates` is installed.

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
    'rate_base_bp' => 1000,
    // ... other fields
]);
```

Auto-generates slug if not provided. New offers always land as `draft`; publishing stays an explicit operator decision.

#### applyForOffer

Apply for an offer as an affiliate.

```php
$application = $offerService->applyForOffer(
    $offer,
    $affiliateId,
    'I have relevant traffic for this offer'
);
```

- Creates a new application or returns the existing one
- When the Affiliates identity adapter is bound and either
  `affiliate-network.owner.enabled` or `affiliates.owner.enabled` is true,
  requires a merchant `Affiliate` row or throws `AffiliateProfileRequired`
- Re-application after a rejection throws
  `AIArmada\AffiliateNetwork\Exceptions\ApplicationAlreadySubmittedException`
  until `affiliate-network.applications.cooldown_days` has elapsed, then
  resets the existing row back to `pending` and dispatches
  `ApplicationSubmitted`
- Auto-approves when the offer's `requires_approval` column is false, or when
  `affiliate-network.applications.auto_approve` is true
- A new automatic approval dispatches `ApplicationSubmitted` first, then
  `ApplicationApproved` after the enclosing database transaction commits
- Pending creation and cooldown reapplication dispatch only `ApplicationSubmitted`;
  returning an existing application dispatches neither event

#### approveApplication

Approve a pending application.

```php
$application = $offerService->approveApplication($application, $reviewerId);
```

Locks and rechecks the owner-scoped application within a transaction. An already
approved application is returned unchanged, preserving its reviewer and approval
timestamps and emitting no new event. A new approval dispatches
`ApplicationApproved` only after the enclosing transaction commits; rollback
discards it.

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
$isApproved = $offerService->isApprovedForOffer($offer, $affiliateId);
// Returns: bool
```

#### getApprovedOffers

Get all active offers an affiliate is approved for.

```php
$offers = $offerService->getApprovedOffers($affiliateId);
// Returns: Collection<AffiliateOffer> — published offers with an approved
// network application (same rule as isApprovedForOffer()).
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

Create a deep link for an affiliate. Throws unless the offer is active
(published + within its `starts_at`/`ends_at` window) and, when the offer
requires approval, the affiliate is approved for it. `target_url` must be
an http(s) URL. The link inherits the offer currency for revenue attribution.

```php
$link = $linkService->createLink($offer, $affiliateId, [
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

Generate a clean public tracking URL.

```php
$url = $linkService->generateTrackingUrl($link);
// Returns: https://yoursite.com/go/aB3dE9fHjKlmN0p
```

Uses the backing tracked link’s public cloaked URL, without signature expiry. Row-level controls and network policy still apply.

#### resolveLink

Resolve a link by its tracked slug.

```php
$link = $linkService->resolveLink('aB3dE9fHjKlmN0p');
// Returns: AffiliateOfferLink|null
```

Only returns active links; callers check expiry themselves.

#### recordConversion

Record a conversion with revenue.

```php
// recordConversion(AffiliateOfferLink $link, int $revenueMinor = 0,
//                   ?string $currency = null, ?string $externalReference = null,
//                   LegStatus $status = LegStatus::Posted): ?NetworkConversionLeg
$leg = $linkService->recordConversion($link, 5999, 'USD', 'ORDER-123'); // 5999 minor units
// A new reference increments counters once and posts a NetworkConversionLeg.
// Replays reuse the existing leg without another counter update or event.
// A currency mismatch preserves leg revenue for audit but sets commission,
// fee, and payout to zero; cached link revenue and the event's revenueMinor
// skip the mismatched amount.
// Without a reference, every call updates counters and emits the counter-only
// event, but posts no leg. Returns null when no reference is supplied.
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
//     'currency' => 'USD',
//     'formatted_revenue' => '$2,679.55',
//     'conversion_rate' => 3.6,
//     'revenue_per_click' => 214.36,
// ]
```
Public `resolveLink()` uses an explicit global lookup window. Click and
conversion writes re-enter the link affiliate's owner context before mutating
network attribution counters.

---

## NetworkBooks

The network ledger. Posts, finalizes, and reverses money legs.

```php
use AIArmada\AffiliateNetwork\Services\NetworkBooks;

$books = app(NetworkBooks::class);

$leg = $books->post($link, 89900, 'MYR', 'ORDER-1001');
// Idempotent on (link, reference): replays return the existing leg.
// New legs cannot use LegStatus::Reversed; use reverse() to create a reversal.
// An initial reversed post throws InvalidArgumentException.

$books->confirm($leg);              // provisional -> posted (caller fulfills)
$books->supersede($leg, $reason);   // provisional -> superseded (lost the decider)
$books->reverse($leg, 'refund');    // posted -> reversed + negated companion leg
$books->recountLinkCounters($link); // rebuild counters/revenue from posted legs
```

`confirm()` and `supersede()` accept optional decision metadata. Local checkout
finalization records `attribution_winner` and `attribution_decision_reason` on
the leg so a duplicate postback returns the original decision.

Commission resolution per post: fixed rate wins, else the volume tier
matching cumulative affiliate+offer revenue (including this conversion),
else the base rate. The fee is basis points on commission
(`offer.network_fee_bp`, falling back to `fees.default_bp`); payout is
commission minus fee.

---

## CreatorBalances

Creator earnings derived from posted legs at read time — no balance rows.

```php
use AIArmada\AffiliateNetwork\Services\CreatorBalances;

app(CreatorBalances::class)->for('affiliate-id');
// ['MYR' => 13216, 'USD' => 4000] — currency => payout minor
```

Only `posted` legs count. If volume ever demands a cache, it backfills
exactly from these rows behind this same seam.

---

## Fulfillment

Posted legs go to exactly one payer through the `Fulfillment` contract:

- Engine installed: `EnginePayoutFulfillment` fulfills merchant payouts
  (and posts the merchant ledger row) via the affiliates seam.
- Standalone: `HostManualFulfillment` records the run on the host; the
  operator pays out of band. It does not run automatically when a conversion
  is recorded. Call `fulfill($leg, $reference)` explicitly with a non-empty
  payout reference; only posted legs can be fulfilled.

The engine adapter only reports success when the merchant posting is not
rejected and its commission amount and currency match the network leg. A
missing or altered merchant posting leaves `unfulfillable` and
`reconciliation_required` metadata on the leg, and no success receipt is
returned.

The service provider binds exactly one implementation — mutual exclusion
is structural, so a leg can never be paid twice by two payers.
