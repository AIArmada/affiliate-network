---
title: API Reference
---

# API Reference

Complete reference for enums, DTOs, models, actions, queries, feature APIs,
events, and exceptions.

## Enums (11)

All enums live in `AIArmada\AffiliateNetwork\Data` and are backed by `string`.

| Enum | Cases |
|---|---|
| `ApplicationStatus` | `Pending='pending'`, `Approved='approved'`, `Rejected='rejected'`, `Revoked='revoked'` |
| `CreativeType` | `Banner='banner'`, `Text='text'`, `Image='image'`, `Video='video'`, `Document='document'`, `Email='email'`, `Html='html'` |
| `LegStatus` | `Provisional='provisional'`, `Pending='pending'`, `Posted='posted'`, `Superseded='superseded'`, `Reversed='reversed'` |
| `OfferSource` | `Mirrored='mirrored'`, `Manual='manual'` |
| `OfferStatus` | `Draft='draft'`, `Published='published'`, `Archived='archived'` |
| `OfferVisibility` | `Public='public'`, `Private='private'`, `Unlisted='unlisted'` |
| `PostbackDeliveryStatus` | `Pending='pending'`, `Delivered='delivered'`, `Failed='failed'` |
| `PostbackEventType` | `ConversionRecorded='conversion.recorded'`, `ConversionLocked='conversion.locked'`, `ConversionReversed='conversion.reversed'`, `ConversionAdjusted='conversion.adjusted'` |
| `SettlementStatus` | `Draft='draft'`, `Issued='issued'`, `Paid='paid'`, `Void='void'` |
| `SiteStatus` | `Pending='pending'`, `Verified='verified'`, `Suspended='suspended'`, `Rejected='rejected'` |
| `SiteSyncStatus` | `Never='never'`, `Ok='ok'`, `Partial='partial'`, `Failed='failed'` |

Enum helpers: most enums expose `label(): string` and `color(): string`.
`LegStatus` also exposes `paysOut(): bool` (`Posted` only) and
`isPayableOrPending(): bool`. `CreativeType` exposes `label()`/`color()`.

> **warning**
> Models declare **no** `STATUS_*` constants. Read the enum:
> `$offer->status === OfferStatus::Published`, not a string constant.

## Key DTOs

All DTOs are `final readonly`.

```php
new OfferDiscoveryFilter(
    categoryId: null, search: null, currency: null, minRateBp: null,
    requiresApproval: null, featured: null, country: null, channel: null,
    device: null, sort: 'featured', perPage: 20,
);

new OfferPerformanceMetrics(
    offerId: string, clicks: int, conversions: int, reversedConversions: int,
    revenueMinor: int, payoutMinor: int, conversionRate: float,
    reversalRate: float, epcMinor: float, avgApprovalHours: ?float,
    currency: string,
);

new CreatorBalanceSummary(
    currency: string, pendingMinor: int, payableMinor: int, paidMinor: int,
    reversedMinor: int, netPayableMinor: int,
);

new RestrictionContext(
    country: ?string = null, channel: ?string = null, device: ?string = null,
    orderMinor: ?int = null, currency: ?string = null,
);

new MerchantStatementSummary(
    id: string, siteId: string, statementNumber: string,
    status: SettlementStatus, currency: string, legCount: int,
    grossRevenueMinor: int, commissionMinor: int, feeMinor: int,
    payoutMinor: int, issuedAt: ?CarbonImmutable = null,
    dueAt: ?CarbonImmutable = null, paidAt: ?CarbonImmutable = null,
);

new NetworkAffiliate(
    id: string, code: string, handle: string, email: ?string = null,
    ownerType: ?string = null, ownerId: string|int|null = null,
    userId: ?string = null,
);

new NetworkLegSnapshot(
    id: string, linkId: ?string, offerId: string, siteId: ?string,
    affiliateId: string, linkCode: ?string, revenueMinor: int,
    revenueCurrency: ?string, commissionMinor: int, commissionCurrency: string,
    feeMinor: int, feeBp: int, payoutMinor: int, tierRateBp: ?int,
    tierMinVolumeMinor: ?int, externalReference: string, eventType: string,
    status: LegStatus, locksAt: ?CarbonImmutable, lockedAt: ?CarbonImmutable,
    fulfilledAt: ?CarbonImmutable, statementId: ?string, couponCode: ?string,
    subId: ?string, subId2: ?string, subId3: ?string, metadata: array = [],
    occurredAt: ?CarbonImmutable = null,
);
```

Other DTOs: `NetworkConversionDraft`, `NetworkPostedConversion`,
`AttributionDecision`, `CreatorPostbackResponse`, `FulfillmentReceipt`,
`FulfillmentResult`, `NotificationPayload`, `SiteCatalogEndpoint`,
`SiteVerificationTarget`.

Feature API records (immutable read models): `SiteRecord`, `OfferRecord`,
`CategoryRecord`, `CreativeRecord`, `ApplicationRecord`, `InvitationRecord`,
`DealRateOverride`, `OfferLinkRecord`, `OfferCouponRecord`,
`CreatorPostbackRecord`.

## Models (13)

```php
AffiliateSite::query()->where('status', SiteStatus::Verified)->get();
AffiliateOffer::query()->whereSiteVerified()->where('status', OfferStatus::Published)->get();
AffiliateOfferCategory::query()->whereNull('parent_id')->get();
AffiliateOfferCreative::query()->where('is_active', true)->get();
AffiliateOfferApplication::query()->where('status', ApplicationStatus::Pending)->get();
AffiliateOfferInvitation::query()->whereNull('accepted_at')->get();
AffiliateOfferDeal::query()->where('is_active', true)->get();
AffiliateOfferCoupon::query()->where('is_active', true)->get();
AffiliateOfferLink::query()->where('is_active', true)->get();
NetworkConversionLeg::query()->where('status', LegStatus::Posted)->get();
MerchantStatement::query()->where('status', SettlementStatus::Draft)->get();
CreatorPostbackEndpoint::query()->where('is_active', true)->get();
CreatorPostbackDelivery::query()->where('status', PostbackDeliveryStatus::Pending)->get();
```

| Model | Key methods |
|---|---|
| `AffiliateSite` | `isVerified()`, `isPending()`, `isSuspended()`, `isRejected()`, `hasCatalogToken()`, `matchesCatalogToken(?string)`, `toRecord()` |
| `AffiliateOffer` | `isDraft()`, `isActive()`, `wasActiveAt(CarbonImmutable)`, `isFixed()`, `formattedRate()`, `toRecord()`, `scopeWhereSiteVerified()`, `normalizeVolumeTiers(?array,?string)`, `rateBlockColumns()` |
| `AffiliateOfferCategory` | relations `parent()`, `children()`, `offers()`, `owner()`, `toRecord()` |
| `AffiliateOfferCreative` | `getAssetUrl()`, `registerMediaCollections()`, `toRecord()` |
| `AffiliateOfferApplication` | `isPending()`, `isApproved()`, `toRecord()` |
| `AffiliateOfferInvitation` | `isActive()`, `toRecord()` |
| `AffiliateOfferDeal` | `isActiveAt(CarbonImmutable)`, `toOverride()` |
| `AffiliateOfferCoupon` | `isActiveAt(CarbonImmutable)`, `toRecord()` |
| `AffiliateOfferLink` | `isExpired()`, `trackedSlug()`, `toRecord()` |
| `NetworkConversionLeg` | `paysOut()`, `toSnapshot()` |
| `MerchantStatement` | `toSummary()` |
| `CreatorPostbackEndpoint` | `subscribesTo(PostbackEventType)`, `eventTypes()`, `toRecord()` |
| `CreatorPostbackDelivery` | relation `endpoint()`, casts `status` |

## Action signatures (condensed)

```php
// Sites
app(CreateSite::class)->execute(array $data): AffiliateSite
app(RegisterSite::class)->execute(Model $owner, array $data): AffiliateSite
app(UpdateSite::class)->execute(AffiliateSite $site, array $data): AffiliateSite
app(VerifySite::class)->execute(AffiliateSite $site, ?string $method = null): AffiliateSite
app(VerifySiteByStrategy::class)->execute(AffiliateSite $site, string $method): AffiliateSite
app(VerifySiteByStrategy::class)->getInstructions(AffiliateSite $site, string $method): array
app(GenerateVerificationToken::class)->execute(AffiliateSite $site): string
app(IssueCatalogToken::class)->execute(AffiliateSite $site): string
app(RotateCatalogToken::class)->execute(AffiliateSite $site): string
app(RejectSite::class)->execute(AffiliateSite $site): AffiliateSite
app(SuspendSite::class)->execute(AffiliateSite $site): AffiliateSite
app(ReinstateSite::class)->execute(AffiliateSite $site): AffiliateSite
app(AdjustMerchantPrepaidBalance::class)->execute(string $siteId, int $deltaMinor): int
app(DeleteSite::class)->execute(AffiliateSite $site): bool

// Catalog
app(CreateOffer::class)->execute(SiteRecord $site, array $data): AffiliateOffer
app(SubmitOffer::class)->execute(SiteRecord $site, array $data): AffiliateOffer
app(UpdateOffer::class)->execute(AffiliateOffer $offer, array $data, bool $fromCatalogSync = false): AffiliateOffer
app(PublishOffer::class)->execute(AffiliateOffer $offer): AffiliateOffer
app(ArchiveOffer::class)->execute(AffiliateOffer $offer): AffiliateOffer
app(ArchiveExpiredOffers::class)->execute(CarbonImmutable $threshold, bool $dryRun = false): int
app(SyncSiteOffers::class)->execute(SiteRecord $site, ?string $programId = null): array
app(SyncOfferCreatives::class)->execute(AffiliateOffer $offer, array $creatives): void
app(CreateCategory::class)->execute(array $data): AffiliateOfferCategory
app(UpdateCategory::class)->execute(AffiliateOfferCategory $category, array $data): AffiliateOfferCategory
app(CreateCreative::class)->execute(AffiliateOffer $offer, array $data): AffiliateOfferCreative
app(UpdateCreative::class)->execute(AffiliateOfferCreative $creative, array $data): AffiliateOfferCreative

// Enrollment
app(ApplyToOffer::class)->execute(string $offerId, string $affiliateId, ?string $reason = null, ?string $invitationToken = null, ?RestrictionContext $restrictionContext = null): AffiliateOfferApplication
app(ApproveApplication::class)->execute(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
app(RejectApplication::class)->execute(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
app(RevokeApplication::class)->execute(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
app(InviteAffiliateToOffer::class)->execute(string $offerId, ?string $affiliateId = null, ?string $invitedBy = null, ?CarbonImmutable $expiresAt = null, bool $autoApprove = true, array $metadata = []): AffiliateOfferInvitation
app(AcceptOfferInvitation::class)->execute(string $token, string $affiliateId, ?string $reason = null): AffiliateOfferApplication
app(UpsertOfferDeal::class)->execute(string $offerId, string $affiliateId, array $data): AffiliateOfferDeal
app(RevokeOfferDeal::class)->execute(AffiliateOfferDeal $deal): AffiliateOfferDeal

// Attribution
app(CreateOfferLink::class)->execute(string $offerId, string $affiliateId, array $options = []): AffiliateOfferLink
app(CreateCreativeLink::class)->execute(string $creativeId, string $affiliateId, array $options = []): AffiliateOfferLink
app(AssignOfferCoupon::class)->execute(string $offerId, string $affiliateId, string $code, ?CarbonImmutable $startsAt = null, ?CarbonImmutable $endsAt = null, ?array $metadata = null): AffiliateOfferCoupon
app(RevokeOfferCoupon::class)->execute(string $couponId): void

// Ledger
app(RecordNetworkConversion::class)->execute(string $offerId, string $affiliateId, int $revenueMinor, ?string $currency, string $externalReference, string $eventType = 'purchase', LegStatus $status = LegStatus::Posted, ?string $linkId = null, ?string $couponCode = null, ?string $linkCode = null, ?string $subId = null, ?string $subId2 = null, ?string $subId3 = null, array $metadata = []): NetworkConversionLeg
app(PostConversionLeg::class)->execute(string $offerId, string $affiliateId, int $revenueMinor, ?string $currency, string $externalReference, string $eventType = 'purchase', LegStatus $status = LegStatus::Posted, ?string $linkId = null, ?string $couponId = null, ?string $couponCode = null, ?string $siteId = null, ?string $linkCode = null, ?string $subId = null, ?string $subId2 = null, ?string $subId3 = null, array $metadata = [], ?CarbonImmutable $occurredAt = null): NetworkConversionLeg
app(ConfirmConversionLeg::class)->execute(NetworkConversionLeg $leg, array $decisionMetadata = []): NetworkConversionLeg
app(SupersedeConversionLeg::class)->execute(NetworkConversionLeg $leg, string $reason, array $decisionMetadata = []): NetworkConversionLeg
app(LockMaturedLegs::class)->execute(?CarbonImmutable $asOf = null): list<string>
app(ReverseConversionLeg::class)->execute(string $legId, string $reason): NetworkConversionLeg
app(AdjustConversionLeg::class)->execute(string $legId, string $adjustmentReference, int $deltaRevenueMinor, string $reason): NetworkConversionLeg
app(FulfillConversionLeg::class)->execute(string $legId, ?string $reference = null): NetworkConversionLeg
app(GenerateMerchantStatement::class)->execute(string $siteId, string $currency, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): MerchantStatement
app(IssueMerchantStatement::class)->execute(string $statementId): MerchantStatement
app(MarkMerchantStatementPaid::class)->execute(string $statementId, ?string $paymentReference = null): MerchantStatement
app(VoidMerchantStatement::class)->execute(string $statementId): MerchantStatement
app(ReconcileOfferLedger::class)->execute(string $offerId, bool $repairCounters = false): array

// Postbacks
app(ConfigureCreatorPostback::class)->execute(string $affiliateId, array $data): CreatorPostbackEndpoint
app(DeleteCreatorPostback::class)->execute(string $endpointId): void
app(EnqueueCreatorPostbacks::class)->execute(NetworkLegSnapshot $leg, PostbackEventType $eventType): int
app(DeliverCreatorPostback::class)->execute(string $deliveryId): CreatorPostbackDelivery
```

## Feature APIs

```php
app(SitesApi::class)->findByIdOrDomainUnscoped($identifier);        // ?SiteRecord
app(SitesApi::class)->isVerified($siteId);                          // bool
app(SitesApi::class)->matchesCatalogToken($siteId, $token);         // bool

app(CatalogApi::class)->findOfferUnscoped($offerId);                // ?OfferRecord
app(CatalogApi::class)->evaluateRestrictions($offer, $context);     // ?string
app(CatalogApi::class)->fallbackUrlForOffer($offerId);              // ?string

app(EnrollmentApi::class)->isApprovedForOffer($offerId, $affiliateId);    // bool
app(EnrollmentApi::class)->approvedOfferIds($affiliateId, 500);           // array
app(EnrollmentApi::class)->applicationStatusMap($affiliateId, $offerIds); // array
app(EnrollmentApi::class)->activeDealFor($offerId, $affiliateId);         // ?DealRateOverride

app(AttributionApi::class)->resolveLinkBySlug($slug);               // ?OfferLinkRecord
app(AttributionApi::class)->resolveCouponForSite($siteId, $code);   // ?OfferCouponRecord
app(AttributionApi::class)->linkStats($linkId);                     // array
app(AttributionApi::class)->offerLinkTotals($offerIds);             // array

app(LedgerApi::class)->isOfferCapped($offerId, $affiliateId, $payoutMinor); // ?string
app(LedgerApi::class)->offerConversionMetrics($offerIds, $since);           // array

app(PostbacksApi::class)->endpointsFor($affiliateId, $offerId, $eventType); // array
app(PostbacksApi::class)->deliveryStatus($deliveryId);                      // ?array
```

## Routes

Redirects are served by `aiarmada/links`: `GET /go/{slug}` (unsigned public
URLs). The network binds an `OfferLinkGate` that returns `404` for unknown slugs
and `410` for inactive links/offers, unverified sites, or missing approvals,
falling back to the offer/site fallback URL when one exists.

Merchant postback routes register when `postbacks.enabled` is true:

| Method | Path | Purpose |
|---|---|---|
| `POST` | `{prefix}/conversions` | Report a conversion |
| `POST` | `{prefix}/conversions/void` | Void/refund a conversion |
| `POST` | `{prefix}/conversions/adjust` | Adjust a conversion |

## Events

```php
OfferCreated(string $offerId, string $siteId, string $name, string $slug, string $status, ?string $ownerType, string|int|null $ownerId)
OfferUpdated(...)                 // same shape
OfferInvitationCreated(string $invitationId, string $offerId, ?string $affiliateId, string $token)
ApplicationSubmitted(string $applicationId, string $offerId, string $offerName, string $siteId, string $affiliateId, string $status, ?string $ownerType, string|int|null $ownerId)
ApplicationApproved(...)          // same shape
NetworkConversionRecorded(NetworkLegSnapshot $leg, int $counterRevenueMinor, ?string $currency, ...)
ConversionLocked(NetworkLegSnapshot $leg)
ConversionReversed(NetworkLegSnapshot $originalLeg, NetworkLegSnapshot $reversalLeg, string $reason)
ConversionAdjusted(NetworkLegSnapshot $originalLeg, NetworkLegSnapshot $adjustmentLeg, string $reason)
MerchantStatementIssued(MerchantStatementSummary $statement)
```

`NetworkConversionRecorded` fires only when the leg is newly created and is not
`provisional`. It does not mean the leg is payable: check
`$leg->status->paysOut()`.

## Exceptions

| Exception | Raised when |
|---|---|
| `ApplicationAlreadySubmittedException` | Reapplication during cooldown, or after revocation |
| `AffiliateProfileRequired` | Affiliate write without a merchant `Affiliate` row (combined owner installs) |
| `AffiliatesNotInstalled` | `affiliate()` relation used without a bound affiliate model |
| `CapExceededException::forOffer($offerId, $reason)` | Offer/site cap reached |
| `RestrictionViolatedException::forOffer($offerId, $reason)` | Traffic restriction violated |
| `OfferNotFoundException` | Offer lookup fails |
| `RuntimeException` | Cross-owner owner-relation assertion, invalid state transition, or missing prerequisite |
| `InvalidArgumentException` | Non-empty reference/delta/statement state violations |

## Verification

- `php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network`
- `./vendor/bin/phpstan analyse -c packages/affiliate-network/tools/package-phpstan.neon --autoload-file=packages/affiliate-network/tools/Architecture/PackageOwnedWriteRule.php --no-progress`
- `./vendor/bin/pest --parallel tests/src/AffiliateNetwork`
