---
title: Usage
---

# Usage

## Catalog sync (merchant program mirror)

Mirror a merchant program as offers instead of hand-typing rates:

```php
use AIArmada\AffiliateNetwork\Services\OfferImportService;

$result = app(OfferImportService::class)->sync($site, $programId);
// ['created' => 2, 'updated' => 0, 'skipped' => 5, 'locked' => 0, 'failed' => 0]
```

- Owned site (shared DB): leave `catalog_url` empty — reads `affiliates` directly
  through its read-only catalog snapshot; no commission or payout rows are
  written.
- Unowned site: set `catalog_url` (e.g. `https://merchant.com/api/affiliates`)
  + `catalog_token_encrypted = encrypt($token)`, then sync with
  `php artisan affiliate-network:sync-offers {site} --program={id}`,
  or omit `--program` to sync every available program on the site.
- Upserts by `(site_id, external_program_id, subject_key)`; unchanged
  checksums skip; manual offers (`external_program_id` null) are never touched.
- Imported rates are the fully-resolved **base** (product/category/program
  rules folded); volume/promotions ride along in `volume_tiers` /
  `active_promotions` columns.
- **Rate lock:** editing any rate field on an offer flips `source` to
  `manual`, and sync holds those rates back (reported as `locked`) instead
  of silently reverting them. Non-rate fields still mirror. Flip
  `source` back to `synced` to re-apply catalog rates on next sync.
- One bad subject never aborts the run: failures are counted as `failed`
  and the site is stamped `partial`. Runs are capped by
  `sync.max_subjects` (per program) and `sync.max_programs` (per `syncAll`).

The importer has one `resolveField(source, local, remote)` precedence helper:
local syncs prefer the local value and remote syncs prefer the remote value,
with null fallback. Imported local offers retain the core program ID in
`external_program_id`; marketplace enrollment links to that existing program
through `affiliates` and never creates a duplicate program or network
application. Remote offers use the network application flow.

Catalog reads are scoped to the synced site's owner: local syncs only see
that owner's programs, never whatever ambient context the caller runs in.
The `sync-offers` command enters the site owner context itself, so it works
with owner mode enabled and no ambient owner (plain console).

## Canonical API: Actions

The canonical orchestration surface is the `Actions` tree. Prefer these over direct service calls:

### Create an Offer

```php
use AIArmada\AffiliateNetwork\Actions\CreateOffer;

$offer = app(CreateOffer::class)->execute($site, [
    'name' => 'Summer Sale Campaign',
    'description' => '20% off summer collection',
    'rate_base_bp' => 1000, // 10% in basis points
    'cookie_days' => 30,
    'landing_url' => 'https://mystore.com/summer-sale',
    'is_public' => true,
    'requires_approval' => true,
]);
```

### Update an Offer

```php
use AIArmada\AffiliateNetwork\Actions\UpdateOffer;

app(UpdateOffer::class)->execute($offer, [
    'rate_base_bp' => 1500,
    'is_public' => false,
]);
```

### Apply to Offer & Approve

```php
use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Actions\ApproveApplication;

$application = app(ApplyToOffer::class)->execute(
    $offer,
    $affiliate,
    'I have a fashion blog with 100k monthly visitors'
);

app(ApproveApplication::class)->execute($application, auth()->id());
```

### Record a Conversion

```php
use AIArmada\AffiliateNetwork\Actions\RecordNetworkConversion;

$leg = app(RecordNetworkConversion::class)->execute($link, 5999, 'USD', 'ORDER-123'); // $59.99 in cents
```

Pass the conversion currency whenever it is known. When it differs from the
link currency, the conversion is counted but revenue is skipped (and logged)
so totals never mix currencies silently.

Pass an external reference (usually the order number) to post a money leg:
commission follows the offer terms (fixed amount, volume tier, or basis
points of revenue), the network fee is carved out, and the posted leg is
handed to fulfillment exactly once. Replays of the same reference reuse the
existing leg instead of double-counting. Without a reference only the link
counters move and no leg posts. Use
`NetworkLedgerReconciliationService::reconcileLink()` / `reconcileOffer()`
to prove counters, legs, and the merchant ledger agree.

## Money Legs & Fees

Every conversion with an external reference posts one
`NetworkConversionLeg`: revenue, commission, fee, and payout in minor units,
plus the tier that priced it. Legs are append-only — balances, counters,
and reconciliation all derive from them:

- **Fee:** `offer.network_fee_bp` overrides
  `affiliate-network.fees.default_bp` (default `0`). The fee is basis
  points on commission; payout is commission minus fee.
- **Volume tiers:** `offer.volume_tiers` is a list of
  `{min_volume_minor, rate_bp, currency?}`. The highest tier whose floor
  the affiliate's cumulative offer revenue clears wins; otherwise the
  base rate applies.
- **Reversals:** `NetworkBooks::reverse($leg, $reason)` marks the leg
  reversed and posts a negated companion leg. Reversed legs never pay.
- **Balances:** `CreatorBalances::for($affiliateId)` sums posted-leg
  payouts per currency at read time — no balance rows to drift.
- **Fulfillment:** posted legs go to exactly one payer. With
  `aiarmada/affiliates` installed the engine fulfills merchant payouts;
  standalone installs record runs on the host
  (`HostManualFulfillment`). The provider enforces mutual exclusion, so
  two payers can never both be active.

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

| Method | Strategy Implementation | Description |
|--------|------------------------|-------------|
| `dns` | `DnsVerificationStrategy` | TXT record on domain |
| `meta_tag` | `MetaTagVerificationStrategy` | Meta tag in HTML head |
| `file` | `FileVerificationStrategy` | File at `/.well-known/affiliate-network-verify.txt` |

New verification methods can be added by implementing `SiteVerificationStrategyInterface` and registering through the container.

## Offer Statuses

`AffiliateOffer::$status` is an `OfferStatus` enum:

| Status | Value | Description |
|--------|-------|-------------|
| `Draft` | `draft` | Not published |
| `Published` | `published` | Live and accepting traffic |
| `Archived` | `archived` | Paused or retired |

Submitting an offer always lands as `draft`; publishing stays an explicit
operator decision. Expired offers are archived by the
`affiliate-network:archive-expired` command.

### Commission Rates

```php
// Percentage commission (basis points: 1000 = 10%)
$offer = AffiliateOffer::create([
    'rate_base_bp' => 1500, // 15%
]);

// Fixed commission (minor units: 500 = $5.00)
$offer = AffiliateOffer::create([
    'rate_fixed_minor' => 500, // $5.00
    'currency' => 'USD',
]);

// With volume tiers and promotions (structured, synced from catalog)
$offer = AffiliateOffer::create([
    'rate_base_bp' => 1000,
    'volume_tiers' => [['min_volume_minor' => 100000, 'rate_bp' => 1500, 'currency' => 'USD']],
    'active_promotions' => [['id' => 'promo-1', 'name' => 'Spring', 'ends_at' => null]],
]);

$offer->formattedRate(); // "15.00%" or "$5.00"
```

### Check Application Status

```php
use AIArmada\AffiliateNetwork\Services\OfferManagementService;

$offerService = app(OfferManagementService::class);

$isApproved = $offerService->isApprovedForOffer($offer, $affiliateId);

// Get all approved offers for an affiliate (approved network applications)
$approvedOffers = $offerService->getApprovedOffers($affiliateId);
```

## Tracking Links

### Generate a Tracking Link

```php
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

$linkService = app(OfferLinkService::class);

$link = $linkService->createLink($offer, $affiliateId, [
    'target_url' => 'https://mystore.com/product/123',
    'sub_id' => 'blog-post-summer',
    'sub_id_2' => 'sidebar-banner',
]);

// Get tracking URL (signed, TTL from links config)
$trackingUrl = $linkService->generateTrackingUrl($link);
// https://yoursite.com/go/aB3dE9fHjKlmN0p?signature=xxx&expires=xxx
```

Every offer link rides on a signed tracked link (`aiarmada/links`): the
redirect appends `anl=<slug>` plus sub IDs to the destination, captures a raw
click event, and increments the link counter through a `LinkClicked`
listener. Unknown slugs return `404`; deactivated, expired, or
policy-blocked links (inactive offer, unverified site, missing approval)
return `410`.

### Track Conversions

```php
// Record a conversion with revenue (pass the conversion currency)
$linkService->recordConversion($link, 5999, 'USD'); // $59.99 in cents

// Get link statistics
$stats = $linkService->getStats($link);
// [
//     'clicks' => 1250,
//     'conversions' => 45,
//     'revenue' => 267955, // cents
//     'currency' => 'USD',
//     'formatted_revenue' => '$2,679.55',
//     'conversion_rate' => 3.6,
//     'revenue_per_click' => 214.36,
// ]
```

### Tracking Model Notes

`OfferLinkService::recordConversion()` updates the package's own `AffiliateOfferLink` counters and revenue totals, and — when an external reference is passed — posts a `NetworkConversionLeg` through `NetworkBooks` and hands it to fulfillment. Merchant-ledger posting (engine `AffiliateConversion` rows with `origin: marketplace`, linked back via `source_ref`) happens in the fulfillment step when `aiarmada/affiliates` is installed, never inline in the counter update.

Links inherit the offer currency at creation. When a conversion arrives in a
different currency, the conversion is counted but its revenue is skipped (and
logged as `affiliate-network.conversion.currency_mismatch`) so link and
network totals never mix currencies.

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

1. **Tracking**: When a user visits your site with a network link parameter (default: `anl`), the `TrackNetworkLinkCookie` middleware captures the link identifier and stores it in an encrypted cookie.
2. **Attribution**: The cookie persists based on the configured lifetime (default: 30 days).
3. **Conversion**: When an order is completed, the orders side triggers a `CommissionAttributionRequired` event.
4. **Provisional leg**: `RecordProvisionalNetworkConversion` reads the attribution cookie and posts a `provisional` leg — money sketched, nothing payable yet.
5. **Last-touch decider**: `FinalizeNetworkAttribution` compares the engine touch against the network touch. An engine win supersedes the provisional leg; a network win confirms and fulfills it. When the engine abstains — or isn't installed — the network wins by default. Every decision is recorded, so exactly one side ever pays.

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
