---
title: Usage
---

# Usage

This guide covers every workflow with the exact Modular Dune class names and
`execute()` signatures. All writes flow through feature `Actions`; reads flow
through feature APIs and queries.

## Quick start

```php
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\PublishOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateOfferLink;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;

$site = app(SitesApi::class)->findById($siteId);        // ?SiteRecord

$offer = app(CreateOffer::class)->execute($site, [
    'name' => 'Summer Sale',
    'rate_base_bp' => 1000,            // 10%
    'visibility' => 'public',
    'requires_approval' => true,
]);

$offer = app(PublishOffer::class)->execute($offer);

$application = app(ApplyToOffer::class)->execute(
    (string) $offer->getKey(),
    $affiliateId,
    'Fashion blog, 100k monthly visitors',
);

app(ApproveApplication::class)->execute($application, $reviewerId);

$link = app(CreateOfferLink::class)->execute((string) $offer->getKey(), $affiliateId, [
    'target_url' => 'https://mystore.com/summer-sale',
    'sub_id' => 'blog-post-1',
]);

$leg = app(RecordNetworkConversion::class)->execute(
    (string) $offer->getKey(),
    $affiliateId,
    5999,          // $59.99 in minor units
    'USD',
    'ORDER-123',
);
```

---

## 1. Site lifecycle and verification

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\CreateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\GenerateVerificationToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySiteByStrategy;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\IssueCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;

$site = app(CreateSite::class)->execute([
    'name' => 'My Store',
    'domain' => 'mystore.com',
    'default_locking_days' => 30,
    'prepaid_balance_minor' => 500_000,     // $5,000 prepaid
    'budget_currency' => 'USD',
    'monthly_spend_cap_minor' => 200_000,   // $2,000 / month
    'require_settled_for_payout' => true,
]);

$token = app(GenerateVerificationToken::class)->execute($site);

// Verification instructions + workflow
$instructions = app(VerifySiteByStrategy::class)->getInstructions($site, 'dns');
$site = app(VerifySiteByStrategy::class)->execute($site, 'dns'); // throws on failure

// Catalog/postback token (plaintext shown once; stored encrypted)
$plain = app(IssueCatalogToken::class)->execute($site);
$rotated = app(RotateCatalogToken::class)->execute($site); // previous token dies immediately
```

Supported verification methods are the extension slot
`site.verification_strategy`: `dns`, `meta_tag`, `file`.

Operator transitions:

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RejectSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\SuspendSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\ReinstateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySite;

app(VerifySite::class)->execute($site, 'dns');   // pending -> verified
app(SuspendSite::class)->execute($site);          // verified -> suspended
app(ReinstateSite::class)->execute($site);        // suspended -> verified
app(RejectSite::class)->execute($site);           // pending -> rejected
```

Merchant self-registration (owner is the registering user/tenant):

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RegisterSite;

$site = app(RegisterSite::class)->execute($owner, [
    'name' => 'My Store',
    'domain' => 'mystore.com',
]);
```

> **warning**
> `RegisterSite` always creates a `pending` site. Re-registering a domain the
> same owner had `rejected` resubmits it; any other duplicate is refused.

---

## 2. Catalog sync (merchant program mirror)

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncSiteOffers;

$result = app(SyncSiteOffers::class)->execute($site, $programId);
// ['created' => 2, 'updated' => 0, 'skipped' => 5, 'locked' => 0, 'failed' => 0]

$result = app(SyncSiteOffers::class)->execute($site);       // sync every program
$result = app(SyncSiteOffers::class)->syncAll($site);       // explicit equivalent
```

- Owned site (shared DB): leave `catalog_url` empty — reads `affiliates` directly,
  writes no commission or payout rows.
- Remote site: set `catalog_url` + `catalog_token_encrypted = encrypt($token)`.
- Upserts by `(site_id, external_program_id, subject_key)`; unchanged checksums
  skip; manual offers (`external_program_id` null) are never touched.
- Imported offers land as `draft`, `source = mirrored`.
- Editing any rate-block field (`rate_base_bp`, `rate_fixed_minor`, `currency`,
  `cookie_days`, `volume_tiers`, `active_promotions`) flips `source` to
  `manual`; later syncs hold those rates back (`locked`).
- One bad subject never aborts the run; the site is stamped `partial`.

---

## 3. Offers

### Create, update, publish, archive

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\PublishOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\ArchiveOffer;

$offer = app(CreateOffer::class)->execute($site, [
    'name' => 'Summer Sale',
    'description' => '20% off the summer collection',
    'rate_base_bp' => 1000,                 // 10%
    'currency' => 'USD',
    'cookie_days' => 30,
    'visibility' => 'public',
    'requires_approval' => true,
    'landing_url' => 'https://mystore.com/summer-sale',
]);

$offer = app(UpdateOffer::class)->execute($offer, [
    'rate_base_bp' => 1500,
    'visibility' => 'private',
]);

$offer = app(PublishOffer::class)->execute($offer);   // published + published_at
$offer = app(ArchiveOffer::class)->execute($offer);   // archived + archived_at
```

`CreateOffer` auto-generates the slug. New offers land as `draft` unless you
explicitly pass a status, and publishing requires a verified site.

### Volume tiers and event-based CPA

```php
$offer = app(CreateOffer::class)->execute($site, [
    'name' => 'Tiered plan',
    'rate_base_bp' => 1000,
    'volume_tiers' => [
        ['min_volume_minor' => 100_000, 'rate_bp' => 1500, 'currency' => 'USD'],
        ['min_volume_minor' => 500_000, 'rate_bp' => 2000, 'currency' => 'USD'],
    ],
    // Price each event independently (multi-event CPA)
    'event_rates' => [
        'signup'   => ['rate_fixed_minor' => 500],
        'lead'     => ['rate_base_bp' => 300],
        'purchase' => ['rate_base_bp' => 1000],
    ],
]);
```

The highest tier whose floor the affiliate's cumulative offer revenue clears
wins. `event_rates` is keyed by the conversion `event_type` and wins over the
base rate for that event.

### Caps and fallback redirects

```php
$offer = app(CreateOffer::class)->execute($site, [
    'name' => 'Capped campaign',
    'rate_fixed_minor' => 1000,
    'daily_conversion_cap' => 100,
    'monthly_conversion_cap' => 1_000,
    'total_conversion_cap' => 10_000,
    'per_affiliate_conversion_cap' => 100,
    'monthly_payout_cap_minor' => 100_000,
    'total_payout_cap_minor' => 1_000_000,
    'fallback_url' => 'https://mystore.com/sale',
    'fallback_offer_id' => $otherOfferId,
]);
```

`CheckOfferCaps::check($offerId, $affiliateId, $payoutMinor)` throws
`CapExceededException` (reason: `daily_conversion_cap`,
`monthly_conversion_cap`, `total_conversion_cap`,
`per_affiliate_conversion_cap`, `monthly_payout_cap_minor`,
`total_payout_cap_minor`, `site_monthly_spend_cap_minor`, or
`site_prepaid_balance`). Link gates fall back to `offer.fallback_url`, then
`offer.fallback_offer_id`, then `links.default_fallback_url`.

### Restrictions and active promotions

```php
$offer = app(CreateOffer::class)->execute($site, [
    'name' => 'Restricted campaign',
    'rate_base_bp' => 1200,
    'restrictions' => [
        'countries' => ['MY', 'SG'],
        'channels' => ['web'],
        'devices' => ['desktop', 'mobile'],
    ],
    'active_promotions' => [
        [
            'id' => 'spring-boost',
            'name' => 'Spring boost',
            'starts_at' => '2026-03-01T00:00:00+00:00',
            'ends_at' => '2026-03-31T23:59:59+00:00',
            'bonus_rate_bp' => 500,          // +5%
            // or: 'override_rate_bp', 'bonus_fixed_minor', 'multiplier'
        ],
    ],
]);
```

Restrictions are checked on apply (`RestrictionContext`), on link redirect, and
on conversion posting; violations throw `RestrictionViolatedException`. Active
promotions are applied when a leg is priced and the promotion id is recorded in
leg metadata as `applied_promotion_id`.

### Categories and creatives

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateCreative;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;

$category = app(CreateCategory::class)->execute([
    'name' => 'Fashion',
    'slug' => 'fashion',
    'is_active' => true,
]);

$creative = app(CreateCreative::class)->execute($offer, [
    'type' => 'banner',
    'name' => '728x90 Leaderboard',
    'width' => 728,
    'height' => 90,
    'destination_url' => 'https://mystore.com/summer-sale',
]);

$creative->addMedia(storage_path('app/banners/summer.jpg'))
    ->toMediaCollection('creative_asset');

$creative->getAssetUrl(); // absolute public URL (or imported source URL)
```

Manual assets live only in the single-file `creative_asset` collection on the
`public` disk. Imported creatives store `source_asset_url`; both resolve through
`getAssetUrl()`.

---

## 4. Enrollment: applications, invitations, deals

### Apply, approve, reject, revoke

```php
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RejectApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeApplication;

$application = app(ApplyToOffer::class)->execute(
    $offerId,
    $affiliateId,
    'I have a fashion blog',
    null,                 // optional invitation token
    null,                 // optional RestrictionContext
);

$application = app(ApproveApplication::class)->execute($application, $reviewerId);
$application = app(RejectApplication::class)->execute($application, 'Traffic mismatch', $reviewerId);
$application = app(RevokeApplication::class)->execute($application, 'ToS violation', $reviewerId);
```

- Re-application after rejection is blocked by
  `ApplicationAlreadySubmittedException` until
  `applications.cooldown_days` has elapsed.
- Auto-approval happens when `requires_approval` is false, or
  `applications.auto_approve` is true, or the invitation sets `auto_approve`.
- `ApplicationSubmitted` is dispatched first; `ApplicationApproved` is
  dispatched after commit.

Read enrollment state:

```php
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;

$enrollments = app(EnrollmentApi::class);

$enrollments->isApprovedForOffer($offerId, $affiliateId);   // bool
$enrollments->approvedOfferIds($affiliateId, limit: 500);   // list<string>
$enrollments->applicationStatusMap($affiliateId, $offerIds); // array<string, ApplicationStatus>
$enrollments->activeDealFor($offerId, $affiliateId);        // ?DealRateOverride
```

### Private/unlisted invitations

```php
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\InviteAffiliateToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\AcceptOfferInvitation;
use Carbon\CarbonImmutable;

$invitation = app(InviteAffiliateToOffer::class)->execute(
    $offerId,
    $affiliateId,            // null for a shareable token
    $invitedBy,
    CarbonImmutable::now()->addDays(7),
    autoApprove: true,
);

$application = app(AcceptOfferInvitation::class)->execute($invitation->token, $affiliateId);
```

`private` and `unlisted` offers require an active invitation (or a token) to
apply. Invitations can be revoked with `RevokeOfferInvitation::execute($invitation)`.

### Per-affiliate custom deals

```php
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\UpsertOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeOfferDeal;

$deal = app(UpsertOfferDeal::class)->execute($offerId, $affiliateId, [
    'rate_base_bp' => 2000,
    'rate_fixed_minor' => null,
    'network_fee_bp' => 500,
    'currency' => 'USD',
    'volume_tiers' => [['min_volume_minor' => 0, 'rate_bp' => 2000]],
    'starts_at' => now(),
    'ends_at' => now()->addMonths(3),
]);

app(RevokeOfferDeal::class)->execute($deal);
```

An active deal overrides the offer's fixed rate, tier, base rate, and fee at
posting time.

---

## 5. Tracking links and linkless coupons

### Create links

```php
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateOfferLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateCreativeLink;
use AIArmada\Links\Actions\GenerateLinkUrl;

$link = app(CreateOfferLink::class)->execute($offerId, $affiliateId, [
    'target_url' => 'https://mystore.com/product/123', // must belong to the verified domain
    'sub_id' => 'blog-post-1',
    'sub_id_2' => 'sidebar',
    'sub_id_3' => 'banner-728x90',
    'link_style' => 'branded',     // short | branded
    'link_label' => 'summer',
    'custom_parameters' => ['utm_source=creator'],
    'expires_at' => now()->addMonths(6),
]);

$url = GenerateLinkUrl::run($link->link);  // https://yoursite.com/go/{slug}

$creativeLink = app(CreateCreativeLink::class)->execute($creativeId, $affiliateId);
```

Links are **unsigned public URLs**. There is no signature lifetime; a copied
URL stays usable until row expiry, deactivation, click limits, or policy blocks
it.

> **warning**
> Breaking change: branded links snapshot the creator handle at creation.
> Renaming a creator never rewrites issued links. Both URL segments must match
> the stored link.

### Assign promo coupons (linkless attribution)

```php
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\AssignOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RevokeOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;

$coupon = app(AssignOfferCoupon::class)->execute(
    $offerId,
    $affiliateId,
    'SUMMER10',
    now(),
    now()->addMonths(1),
);

// Resolve at checkout time; returns ?OfferCouponRecord
$couponRecord = app(AttributionApi::class)->resolveCouponForSite($siteId, 'SUMMER10');

app(RevokeOfferCoupon::class)->execute((string) $coupon->getKey());
```

Coupon codes are normalized to uppercase, must match
`^[A-Z0-9_-]{1,64}$`, and are unique per site. Pass the resolved `coupon_code`
to `RecordNetworkConversion` to attribute without a link click.

### Link statistics

```php
$stats = app(AttributionApi::class)->linkStats($linkId);
// ['clicks' => 1250, 'conversions' => 45, 'revenue' => 267955,
//  'currency' => 'USD', 'conversion_rate' => 3.6, 'revenue_per_click' => 214.36]
```

---

## 6. Record conversions and post money legs

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Data\LegStatus;

$leg = app(RecordNetworkConversion::class)->execute(
    offerId: $offerId,
    affiliateId: $affiliateId,
    revenueMinor: 5999,
    currency: 'USD',
    externalReference: 'ORDER-123',
    eventType: 'purchase',            // signup | lead | purchase | ...
    status: LegStatus::Posted,
    linkId: $linkId,                  // optional; omit for coupon-only
    couponCode: null,
    linkCode: null,
    subId: 'blog-post-1',
    subId2: 'sidebar',
    subId3: 'banner-728x90',
    metadata: [],
);
```

- A non-empty `externalReference` is required.
- Idempotent on `(link|coupon, reference, event_type)`; replays return the
  existing leg with no counter update or event.
- On a currency mismatch the conversion is counted but commission/fee/payout are
  zero; the leg keeps reported revenue for audit.
- Commission precedence: active deal → `event_rates[eventType]` → fixed rate →
  volume tier → base rate. The fee is basis points on commission; payout is
  commission minus fee.
- A non-zero locking window turns `Posted` into `Pending` with `locks_at`.
- Automatic engine fulfillment runs only when the site does not require
  settlement; standalone installs stay unfulfilled until the host records a run.

### Lower-level posting

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;

$leg = app(PostConversionLeg::class)->execute(
    offerId: $offerId,
    affiliateId: $affiliateId,
    revenueMinor: 89900,
    currency: 'MYR',
    externalReference: 'ORDER-1001',
    eventType: 'purchase',
    status: LegStatus::Posted,
    linkId: $linkId,
    couponId: null,
    couponCode: null,
    siteId: $siteId,
    linkCode: null,
    subId: null,
    subId2: null,
    subId3: null,
    metadata: [],
    occurredAt: null,
);
```

`PostConversionLeg` is idempotent on the derived idempotency key
(`leg:link:{id}:{reference}:{event}` or `leg:coupon:{id}:{reference}:{event}`).

### Creator balances (derived at read time)

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\CreatorBalancesQuery;

$balances = app(CreatorBalancesQuery::class)->for($affiliateId);
// array<string, CreatorBalanceSummary> keyed by currency

$usd = $balances['USD'];
$usd->pendingMinor;      // pending legs
$usd->payableMinor;      // posted, unfulfilled
$usd->paidMinor;         // posted, fulfilled
$usd->reversedMinor;     // reversed legs
$usd->netPayableMinor;   // payable + clawbacks from reversed fulfilled legs
```

No balance rows are persisted; every number is computed from legs.

---

## 7. Locking window

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;

$lockedIds = app(LockMaturedLegs::class)->execute();          // now
$lockedIds = app(LockMaturedLegs::class)->execute($asOf);      // explicit instant
// list<string> of matured leg ids; each pending leg -> posted + locked_at
```

Precedence for the window: `offer.locking_days` →
`site.default_locking_days` → `locking.default_days`. Matured legs fire
`ConversionLocked`, then fulfil through the engine when eligible.

---

## 8. Merchant statements

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\GenerateMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;
use Carbon\CarbonImmutable;

// Aggregate posted, unstated legs for a site/currency/period
$statement = app(GenerateMerchantStatement::class)->execute(
    $siteId,
    'USD',
    CarbonImmutable::parse('2026-10-01'),
    CarbonImmutable::parse('2026-10-31 23:59:59'),
);

$statement = app(IssueMerchantStatement::class)->execute((string) $statement->getKey());
// draft -> issued, stamps issued_at + due_at (statements.due_days)

$statement = app(MarkMerchantStatementPaid::class)->execute(
    (string) $statement->getKey(),
    'BANK-REF-778',
);

$statement = app(VoidMerchantStatement::class)->execute((string) $statement->getKey());
```

- Only `posted` legs with no `statement_id` and no `parent_leg_id` attach to a
  statement.
- `MarkMerchantStatementPaid` fulfils attached legs only when the site has
  `require_settled_for_payout = true` (pay-when-paid gate).
- Paid or void statements cannot be voided.

Read receivables:

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\MerchantReceivablesQuery;

$receivables = app(MerchantReceivablesQuery::class)->execute();
```

---

## 9. Adjustments and reversals

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ConfirmConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\SupersedeConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\FulfillConversionLeg;

// Partial refund / price correction: -$10.00 revenue, proportional money split
$adjustment = app(AdjustConversionLeg::class)->execute(
    (string) $leg->getKey(),
    'ADJ-1',            // adjustment reference (idempotency)
    -1000,              // delta revenue in minor units (non-zero)
    'partial refund',
);

// Full reversal posts a negated companion leg; reversed legs never pay
$reversed = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'refund');

// Attribution deciders
app(ConfirmConversionLeg::class)->execute($leg, ['attribution_winner' => 'network']);
app(SupersedeConversionLeg::class)->execute($leg, 'engine_won', ['attribution_winner' => 'engine']);

// Explicit fulfillment (standalone hosts)
app(FulfillConversionLeg::class)->execute((string) $leg->getKey(), 'PAYOUT-REG-1');
```

---

## 10. Outbound creator postbacks

```php
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\ConfigureCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\DeleteCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Api\PostbacksApi;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;

$endpoint = app(ConfigureCreatorPostback::class)->execute($affiliateId, [
    'offer_id' => null,                 // null = all offers
    'url_template' => 'https://creator.app/hooks/affiliate?leg={leg_id}&ref={external_reference}&sub1={sub1}&event={event_type}&sig={status}',
    'http_method' => 'POST',            // GET | POST
    'events' => [
        PostbackEventType::ConversionRecorded->value,
        PostbackEventType::ConversionLocked->value,
        PostbackEventType::ConversionReversed->value,
        PostbackEventType::ConversionAdjusted->value,
    ],
    'signing_secret' => 'shared-secret',
    'is_active' => true,
]);

$status = app(PostbacksApi::class)->deliveryStatus($deliveryId);
app(DeleteCreatorPostback::class)->execute((string) $endpoint->getKey());
```

Supported URL tokens: `{leg_id}`, `{offer_id}`, `{link_code}`,
`{external_reference}`, `{event_type}`, `{status}`, `{revenue_minor}`,
`{commission_minor}`, `{payout_minor}`, `{currency}`, `{sub1}`, `{sub2}`,
`{sub3}`, `{coupon_code}`. Tokens are URL-encoded before substitution.

Deliveries are signed with
`X-Affiliate-Network-Signature: hash_hmac('sha256', $resolvedUrl, $secret)`.
Retries are queued; `creator_postbacks.queue` selects the queue.

---

## 11. Marketplace discovery and metrics

```php
use AIArmada\AffiliateNetwork\Data\OfferDiscoveryFilter;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\OfferDiscoveryQuery;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\OfferMetricsQuery;

$offers = app(OfferDiscoveryQuery::class)->execute(new OfferDiscoveryFilter(
    categoryId: $categoryId,
    search: 'summer',
    currency: 'USD',
    minRateBp: 1000,
    requiresApproval: false,
    featured: true,
    country: 'MY',
    channel: 'web',
    device: 'mobile',
    sort: 'epc',          // featured | newest | highest_rate | epc | conversion_rate
    perPage: 24,
));

$metrics = app(OfferMetricsQuery::class)->forOffer($offers[0], windowDays: 30);
// OfferPerformanceMetrics: clicks, conversions, reversedConversions,
// revenueMinor, payoutMinor, conversionRate, reversalRate, epcMinor,
// avgApprovalHours, currency
```

---

## 12. Reconciliation

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReconcileOfferLedger;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\LedgerReconciliationQuery;

$report = app(ReconcileOfferLedger::class)->execute($offerId, repairCounters: true);

$linkReport = app(LedgerReconciliationQuery::class)->reconcileLink($linkId);
// ['link_id','match','network','legs','differences']

$offerReport = app(LedgerReconciliationQuery::class)->reconcileOffer($offerId);
// ['offer_id','match','links','matched_links','differences']
```

---

## Artisan commands

```bash
# Mirror merchant programs as network offers
php artisan affiliate-network:sync-offers {site} [--program={id}]

# Archive offers whose end date passed
php artisan affiliate-network:archive-expired [--older-than=90] [--dry-run]

# Prove network legs, counters, and merchant postings agree
php artisan affiliate-network:reconcile [--offer={offerId}] [--repair] [--fail-on-mismatch]

# Mature pending legs whose locking window elapsed
php artisan affiliate-network:lock-matured [--as-of=...]

# Generate statements for a period (defaults to last month), optionally issue
php artisan affiliate-network:generate-statements [--period-start=...] [--period-end=...] [--issue]
```
