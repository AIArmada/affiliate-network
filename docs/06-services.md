---
title: Services
---

# Services Reference

## Canonical API: Actions

The canonical orchestration surface for affiliate-network is the `Actions` tree. Prefer these over direct service calls:

| Action | Purpose |
|--------|---------|
| `app(RegisterSite::class)->execute($owner, $data)` | Create a merchant site |
| `app(CreateOffer::class)->execute($site, $data)` | Create a new offer |
| `app(UpdateOffer::class)->execute($offer, $data)` | Update an existing offer |
| `app(SubmitOffer::class)->execute($site, $data)` | Merchant-submitted offer (always `draft`) |
| `app(ApplyToOffer::class)->execute($offer, $affiliateId, $reason)` | Apply to an offer |
| `app(ApproveApplication::class)->execute($application, $reviewedBy)` | Approve an application |
| `app(RecordNetworkConversion::class)->execute($link, $revenueMinor, $currency, $externalReference, $status)` | Record a conversion (posts a leg when `$externalReference` is set) |

`ApplyToOffer` takes an affiliate **id string**, not a model, and the third
argument is `$reason`. None of these actions use `AsAction` — call them through
the container with `execute()`.

Events are automatically dispatched by each Action (`OfferCreated`,
`OfferUpdated`, `ApplicationSubmitted`, `ApplicationApproved`,
`NetworkConversionRecorded`).

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

## OfferManagementService

Manages offers and affiliate applications.

This is the discovery-side API. Every offer — local import or remote
mirror — enrolls through the network application flow; joining never
requires, resolves, or creates a merchant-side account. Commission
writes land as network legs (`NetworkBooks`); the merchant ledger only
receives postings through the fulfillment step when `aiarmada/affiliates`
is installed.

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

Auto-generates slug if not provided. `status` defaults to `draft`.

> **warning:**
> `affiliate-network.offers.require_approval` is defined in the shipped config
> but never read by any code path. `createOffer()` does not consult it. Approval
> is decided per offer by the `requires_approval` column, which
> `ApplyToOffer` reads (together with the live
> `affiliate-network.applications.auto_approve` flag).

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
- Re-application after a rejection throws
  `AIArmada\AffiliateNetwork\Exceptions\ApplicationAlreadySubmittedException`
  until `affiliate-network.applications.cooldown_days` has elapsed, then
  resets the existing row back to `pending` and dispatches
  `ApplicationSubmitted`
- Auto-approves when the offer's `requires_approval` column is false, or when
  `affiliate-network.applications.auto_approve` is true

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

Generate a signed tracking URL.

```php
$url = $linkService->generateTrackingUrl($link);
// Returns: https://yoursite.com/go/aB3dE9fHjKlmN0p?signature=xxx&expires=xxx
```

Uses the backing tracked link's signed URL (TTL from `links.routing.signature_ttl_minutes`).

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
// Increments $link->conversions and adds to $link->revenue. On a currency
// mismatch the conversion is counted but revenue is skipped (and logged).
// With a reference, also posts a NetworkConversionLeg and fulfills it, and
// returns it. Returns null when no reference is supplied.
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

$books->confirm($leg);              // provisional -> posted (caller fulfills)
$books->supersede($leg, $reason);   // provisional -> superseded (lost the decider)
$books->reverse($leg, 'refund');    // posted -> reversed + negated companion leg
$books->recountLinkCounters($link); // rebuild counters/revenue from posted legs
```

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
  operator pays out of band.

The service provider binds exactly one implementation — mutual exclusion
is structural, so a leg can never be paid twice by two payers.
