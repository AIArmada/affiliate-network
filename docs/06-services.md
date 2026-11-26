---
title: Services
---

# Services Reference

There is no service layer. Every write is a named feature `Action`; every read is
a feature `Query` or `Api`. Inject them through Laravel's container and call
`execute()` (or the documented read method). None use `AsAction`.

> **warning**
> Breaking change from the pre-Dune layout: `AIArmada\AffiliateNetwork\{Services,Adapters,Strategies,Support}\*`
> no longer exists. Use `Features\<Feature>\Actions`, `Features\<Feature>\Queries`,
> `Features\<Feature>\Api`, `Contracts\*`, `Drivers\*`, and `Platform\*`.

## Signature index by feature

### Sites

| Action | Signature |
|---|---|
| `CreateSite` | `execute(array $data): AffiliateSite` |
| `RegisterSite` | `execute(Model $owner, array $data): AffiliateSite` |
| `UpdateSite` | `execute(AffiliateSite $site, array $data): AffiliateSite` |
| `VerifySite` | `execute(AffiliateSite $site, ?string $method = null): AffiliateSite` |
| `VerifySiteByStrategy` | `execute(AffiliateSite $site, string $method): AffiliateSite`; `getInstructions(AffiliateSite $site, string $method): array`; `strategies(): list<SiteVerificationStrategy>`; `hasStrategy(string $method): bool` |
| `GenerateVerificationToken` | `execute(AffiliateSite $site): string` |
| `RejectSite` / `SuspendSite` / `ReinstateSite` | `execute(AffiliateSite $site): AffiliateSite` |
| `IssueCatalogToken` | `execute(AffiliateSite $site): string` |
| `RotateCatalogToken` | `execute(AffiliateSite $site): string` |
| `RecordSiteSyncStatus` | `execute(string $siteId, SiteSyncStatus $status): void` |
| `AdjustMerchantPrepaidBalance` | `execute(string $siteId, int $deltaMinor): int` |
| `DeleteSite` | `execute(AffiliateSite $site): bool` |

`CreateSite`/`UpdateSite` validate and normalize domain/token input through the
shared `ValidatesSiteInput` concern. Changing a verified site's domain resets it
to `pending`, clears `verified_at`, and mints a fresh verification token.

### Catalog

| Action | Signature |
|---|---|
| `CreateOffer` | `execute(SiteRecord $site, array $data): AffiliateOffer` |
| `SubmitOffer` | `execute(SiteRecord $site, array $data): AffiliateOffer` (always `draft`) |
| `UpdateOffer` | `execute(AffiliateOffer $offer, array $data, bool $fromCatalogSync = false): AffiliateOffer` |
| `PublishOffer` | `execute(AffiliateOffer $offer): AffiliateOffer` |
| `ArchiveOffer` | `execute(AffiliateOffer $offer): AffiliateOffer` |
| `DeleteOffer` | `execute(AffiliateOffer $offer): bool` |
| `ArchiveExpiredOffers` | `execute(CarbonImmutable $threshold, bool $dryRun = false): int` |
| `SyncSiteOffers` | `execute(SiteRecord $site, ?string $programId = null): array`; `sync(SiteRecord $site, string $programId): array`; `syncAll(SiteRecord $site): array` |
| `SyncOfferCreatives` | `execute(AffiliateOffer $offer, array $creatives): void`; `normalize(mixed $creatives): array` |
| `CreateCategory` / `UpdateCategory` | `execute(array $data): AffiliateOfferCategory` / `execute(AffiliateOfferCategory $category, array $data): AffiliateOfferCategory` |
| `CreateCreative` / `UpdateCreative` | `execute(AffiliateOffer $offer, array $data): AffiliateOfferCreative` / `execute(AffiliateOfferCreative $creative, array $data): AffiliateOfferCreative` |
| `DeleteCategory` / `DeleteCreative` | `execute(AffiliateOfferCategory $category): bool` / `execute(AffiliateOfferCreative $creative): bool` |

`CreateOffer` takes a `SiteRecord` **from `SitesApi`**, not an `AffiliateSite`
model. `UpdateOffer` flips `source` to `manual` on rate-block edits unless
called from catalog sync.

### Enrollment

| Action | Signature |
|---|---|
| `ApplyToOffer` | `execute(string $offerId, string $affiliateId, ?string $reason = null, ?string $invitationToken = null, ?RestrictionContext $restrictionContext = null): AffiliateOfferApplication` |
| `ApproveApplication` | `execute(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication` |
| `RejectApplication` | `execute(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication` |
| `RevokeApplication` | `execute(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication` |
| `InviteAffiliateToOffer` | `execute(string $offerId, ?string $affiliateId = null, ?string $invitedBy = null, ?CarbonImmutable $expiresAt = null, bool $autoApprove = true, array $metadata = []): AffiliateOfferInvitation` |
| `AcceptOfferInvitation` | `execute(string $token, string $affiliateId, ?string $reason = null): AffiliateOfferApplication` |
| `RevokeOfferInvitation` | `execute(AffiliateOfferInvitation $invitation): AffiliateOfferInvitation` |
| `UpsertOfferDeal` | `execute(string $offerId, string $affiliateId, array $data): AffiliateOfferDeal` |
| `RevokeOfferDeal` | `execute(AffiliateOfferDeal $deal): AffiliateOfferDeal` |
| `DeleteOfferEnrollments` | `execute(string $offerId): void` |

`ApplyToOffer` takes an affiliate **id string**, not a model. It dispatches
`ApplicationSubmitted` first and `ApplicationApproved` after commit when the
result is auto-approved.

### Attribution

| Action | Signature |
|---|---|
| `CreateOfferLink` | `execute(string $offerId, string $affiliateId, array $options = []): AffiliateOfferLink` |
| `CreateCreativeLink` | `execute(string $creativeId, string $affiliateId, array $options = []): AffiliateOfferLink` |
| `DeactivateOfferLink` | `execute(string $linkId): void` |
| `IncrementLinkClicks` | `execute(string $linkId): void` |
| `RecordLinkCounters` | `execute(string $linkId, int $revenueDeltaMinor, int $conversionDelta = 1): void` |
| `RecountLinkCounters` | `execute(string $linkId, int $conversions, int $revenueMinor): void` |
| `DeleteOfferLinks` | `execute(string $offerId): void` |
| `AssignOfferCoupon` | `execute(string $offerId, string $affiliateId, string $code, ?CarbonImmutable $startsAt = null, ?CarbonImmutable $endsAt = null, ?array $metadata = null): AffiliateOfferCoupon` |
| `RevokeOfferCoupon` | `execute(string $couponId): void` |

`CreateOfferLink` options: `target_url`, `sub_id`, `sub_id_2`, `sub_id_3`,
`is_active`, `metadata`, `link_style` (`short`/`branded`), `link_label`,
`custom_parameters`, `expires_at`.

### Ledger

| Action | Signature |
|---|---|
| `RecordNetworkConversion` | `execute(string $offerId, string $affiliateId, int $revenueMinor, ?string $currency, string $externalReference, string $eventType = 'purchase', LegStatus $status = LegStatus::Posted, ?string $linkId = null, ?string $couponCode = null, ?string $linkCode = null, ?string $subId = null, ?string $subId2 = null, ?string $subId3 = null, array $metadata = []): NetworkConversionLeg` |
| `PostConversionLeg` | `execute(string $offerId, string $affiliateId, int $revenueMinor, ?string $currency, string $externalReference, string $eventType = 'purchase', LegStatus $status = LegStatus::Posted, ?string $linkId = null, ?string $couponId = null, ?string $couponCode = null, ?string $siteId = null, ?string $linkCode = null, ?string $subId = null, ?string $subId2 = null, ?string $subId3 = null, array $metadata = [], ?CarbonImmutable $occurredAt = null): NetworkConversionLeg` |
| `ConfirmConversionLeg` | `execute(NetworkConversionLeg $leg, array $decisionMetadata = []): NetworkConversionLeg` |
| `SupersedeConversionLeg` | `execute(NetworkConversionLeg $leg, string $reason, array $decisionMetadata = []): NetworkConversionLeg` |
| `LockMaturedLegs` | `execute(?CarbonImmutable $asOf = null): list<string>` |
| `ReverseConversionLeg` | `execute(string $legId, string $reason): NetworkConversionLeg` |
| `AdjustConversionLeg` | `execute(string $legId, string $adjustmentReference, int $deltaRevenueMinor, string $reason): NetworkConversionLeg` |
| `FulfillConversionLeg` | `execute(string $legId, ?string $reference = null): NetworkConversionLeg` |
| `CheckOfferCaps` | `checkReason(string $offerId, ?string $affiliateId = null, int $proposedPayoutMinor = 0, ?CarbonImmutable $at = null): ?string`; `check(...): void` |
| `GenerateMerchantStatement` | `execute(string $siteId, string $currency, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): MerchantStatement` |
| `IssueMerchantStatement` | `execute(string $statementId): MerchantStatement`; `assertIssuable(MerchantStatement $statement): void` |
| `MarkMerchantStatementPaid` | `execute(string $statementId, ?string $paymentReference = null): MerchantStatement` |
| `VoidMerchantStatement` | `execute(string $statementId): MerchantStatement` |
| `ReconcileOfferLedger` | `execute(string $offerId, bool $repairCounters = false): array` |
| `RecordOrderAttribution` | `writeTouch(Order $order, ?string $clickedAt): void`; `writeAttribution(Order $order, array $attribution): void`; `writeDecision(Order $order, array $metadata, string $winner, string $reason): void` |

> **warning**
> `RecordNetworkConversion` and `PostConversionLeg` take **scalar IDs** —
> `(string $offerId, string $affiliateId, ...)` — never an `AffiliateOfferLink`
> model. Pass `$link->getKey()` as `$linkId`. The `network` link id may be omitted
> for coupon-only attributions; supply `couponCode` (and `couponId` on
> `PostConversionLeg`) instead.

### Postbacks

| Action | Signature |
|---|---|
| `ConfigureCreatorPostback` | `execute(string $affiliateId, array $data): CreatorPostbackEndpoint` |
| `DeleteCreatorPostback` | `execute(string $endpointId): void` |
| `EnqueueCreatorPostbacks` | `execute(NetworkLegSnapshot $leg, PostbackEventType $eventType): int` |
| `DeliverCreatorPostback` | `execute(string $deliveryId): CreatorPostbackDelivery` |

## Queries

| Query | Method |
|---|---|
| `OfferDiscoveryQuery` | `execute(OfferDiscoveryFilter $filter): list<OfferRecord>` |
| `OfferMetricsQuery` | `forOffer(OfferRecord $offer, int $windowDays = 30): OfferPerformanceMetrics`; `batch(array $offers, int $windowDays = 30): array<string, OfferPerformanceMetrics>` |
| `CreatorBalancesQuery` | `for(string $affiliateId): array<string, CreatorBalanceSummary>` |
| `LedgerReconciliationQuery` | `reconcileLink(string $linkId): array`; `reconcileOffer(string $offerId): array`; `computeCounters(string $linkId): array` |
| `MerchantReceivablesQuery` | `execute(): array` |
| `ResolveLegOwnerQuery` | `forLeg(NetworkConversionLeg $leg): ?Model` |
| `ApplicationStatusQuery` | `isApprovedForOffer`, `isApprovedForOfferUnscoped`, `hasAppliedForOffer`, `applicationStatusForOffer`, `applicationStatusMap`, `approvedOfferIds`, `activeDealFor`, `averageApprovalHoursForOffer` |
| `ApplyToOffer` reads through `CatalogApi`/`SitesApi` — no direct model queries |
| `CreativePayloadQuery` | `execute(string $creativeId, string $linkId): array` |
| `LinkStatsQuery` | `execute(string $linkId): array` |
| `ResolveOfferCouponQuery` | `forSite(string $siteId, string $code, ?CarbonImmutable $at = null): ?AffiliateOfferCoupon` |
| `ResolveOfferLinkQuery` | `byIdUnscoped(string $linkId)`, `bySlug(string $slug)`, `bySlugUnscoped(string $slug, bool $requireActive = false)` |
| `EvaluateActivePromotion` | `evaluate(OfferRecord $offer, CarbonImmutable $at): ?array` |
| `EvaluateOfferRestrictions` | `evaluate(OfferRecord $offer, RestrictionContext $context): ?string` |
| `ResolvePublicOfferQuery` | `find(string $offerId): ?AffiliateOffer` |
| `SiteLookupQuery` | `findById`, `findByIdUnscoped`, `findByIdOrDomainUnscoped` |

## Feature APIs

Sibling features and host code read through these read-only APIs.

```php
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Postbacks\Api\PostbacksApi;
```

### SitesApi
`findById(string): ?SiteRecord`, `findByIdUnscoped(string): ?SiteRecord`,
`findByIdOrDomainUnscoped(string): ?SiteRecord`, `isVerified(string): bool`,
`matchesCatalogToken(string $siteId, ?string $token): bool`,
`recordSyncStatus(string $siteId, SiteSyncStatus $status): void`,
`adjustPrepaidBalance(string $siteId, int $deltaMinor): int`.

### CatalogApi
`findOfferUnscoped(string): ?OfferRecord`, `findCreativeUnscoped(string): ?CreativeRecord`,
`findPublicOffer(string): ?OfferRecord`, `wasOfferActiveAt(string, CarbonImmutable): bool`,
`evaluateRestrictions(OfferRecord, RestrictionContext): ?string`,
`evaluateActivePromotion(OfferRecord, CarbonImmutable): ?array`,
`deleteOffersForSite(string): void`, `fallbackUrlForOffer(string): ?string`.

### EnrollmentApi
`isApprovedForOffer(string, string): bool`, `isApprovedForOfferUnscoped(...)`,
`hasAppliedForOffer(...)`, `applicationStatusForOffer(...): ?ApplicationStatus`,
`applicationStatusMap(string $affiliateId, array $offerIds): array`,
`approvedOfferIds(string $affiliateId, int $limit = 500): array`,
`activeDealFor(string $offerId, string $affiliateId, ?CarbonImmutable $at = null): ?DealRateOverride`,
`deleteForOffer(string): void`, `averageApprovalHoursForOffer(string, CarbonImmutable): ?float`.

### AttributionApi
`findLinkByIdUnscoped(string): ?OfferLinkRecord`, `resolveLinkBySlug(string): ?OfferLinkRecord`,
`findLinkBySlugUnscoped(string, bool $requireActive = false): ?OfferLinkRecord`,
`resolveCouponForSite(string $siteId, string $code, ?CarbonImmutable $at = null): ?OfferCouponRecord`,
`recordLinkCounters(string, int, int = 1): void`, `setLinkCounters(string, int, int): void`,
`linkStats(string): array`, `offerLinkIds(string): array`,
`offerLinkTotals(array): array`, `deleteForOffer(string): void`.

### LedgerApi
`isOfferCapped(string $offerId, ?string $affiliateId = null, int $proposedPayoutMinor = 0): ?string`,
`findLegByIdempotencyKeyUnscoped(string): ?NetworkLegSnapshot`,
`findLegByLinkOrCouponUnscoped(...)`, `offerConversionMetrics(array $offerIds, CarbonImmutable $since): array`.

### PostbacksApi
`endpointsFor(string $affiliateId, ?string $offerId, PostbackEventType $eventType): array`,
`deliveryStatus(string $deliveryId): ?array`.

## Contracts (pure seams)

| Contract | Methods |
|---|---|
| `AffiliateIdentityResolver` | `find(string): ?NetworkAffiliate`, `findAccessible(string): ?NetworkAffiliate`, `lockForWrite(string): void`, `findIdForVerifiedEmail(string): ?string`, `requiresMerchantProfile(): bool`, `hasMerchantProfile(string): bool` |
| `NetworkLedger` | `post(NetworkConversionDraft): ?NetworkPostedConversion`, `findPosted(string, string): ?NetworkPostedConversion`, `voidPosting(string, string, string): ?NetworkPostedConversion`, `rowsForLink(string): array`, `postingsForExternalReference(string): array` |
| `Fulfillment` | `fulfillsAutomatically(): bool`, `fulfill(NetworkLegSnapshot, ?string $reference = null): FulfillmentResult` |
| `CatalogReader` | `snapshot(SiteCatalogEndpoint, string $programId): array`, `programIds(SiteCatalogEndpoint): array` |
| `DnsResolver` | `getTxtRecords(string $hostname): array` |
| `SiteContentClient` | `fetch(string $domain, string $path): ?string` |
| `SiteVerificationStrategy` | `methodKey()`, `label()`, `verify(SiteVerificationTarget): bool`, `getInstructions(SiteVerificationTarget): array` |
| `CreatorPostbackTransport` | `send(string $method, string $url, array $headers = [], ?array $body = null): CreatorPostbackResponse` |
| `NotificationDispatcher` | `dispatchToUser(...)`, `dispatchToOwner(...)` |

## Drivers

- `Drivers/Affiliates\{AffiliatesIdentityDriver, AffiliatesLedgerDriver, AffiliatesCatalogDriver, EnginePayoutFulfillmentDriver}` — bound when the affiliates engine is active.
- `Drivers/UserKeyIdentityDriver` — standalone identity fallback (stable `creator-{hash}` handles).
- `Drivers/HttpCreatorPostbackDriver` — SSRF-guarded outbound creator callbacks.
- `Drivers/HttpSiteContentDriver`, `Drivers/NativeDnsDriver`, `Drivers/RemoteCatalogHttpDriver` — verification and catalog HTTP/DNS.
- `Drivers/HostManualFulfillmentDriver`, `Drivers/CommunicationsNotificationDriver` — standalone fulfillment and optional notifications.

## Events (pure DTO payloads)

All events are immutable `final readonly` classes carrying scalars/DTOs — never
Eloquent models. They are safe to queue.

| Event | Payload |
|---|---|
| `OfferCreated` / `OfferUpdated` | `offerId, siteId, name, slug, status, ownerType?, ownerId?` |
| `OfferInvitationCreated` | `invitationId, offerId, affiliateId?, token` |
| `ApplicationSubmitted` / `ApplicationApproved` | `applicationId, offerId, offerName, siteId, affiliateId, status, ownerType?, ownerId?` |
| `NetworkConversionRecorded` | `NetworkLegSnapshot $leg, int $counterRevenueMinor, ?string $currency, ?string $ownerType, string\|int\|null $ownerId` |
| `ConversionLocked` | `NetworkLegSnapshot $leg` |
| `ConversionReversed` | `NetworkLegSnapshot $originalLeg, NetworkLegSnapshot $reversalLeg, string $reason` |
| `ConversionAdjusted` | `NetworkLegSnapshot $originalLeg, NetworkLegSnapshot $adjustmentLeg, string $reason` |
| `MerchantStatementIssued` | `MerchantStatementSummary $statement` |

> **warning**
> Do not add `InteractsWithSockets` (or any stateful trait) to these events —
> they are `final readonly` and cannot carry mutable properties.

## Notifications

`Notifications\{ApplicationSubmittedNotification, ApplicationApprovedNotification, NetworkConversionNotification}`
are dispatched through `NotificationDispatcher` when
`aiarmada/communications` is installed and `notifications.enabled` is true.

## DTOs

`Data\*` ships 11 backed enums (`ApplicationStatus`, `CreativeType`, `LegStatus`,
`OfferSource`, `OfferStatus`, `OfferVisibility`, `PostbackDeliveryStatus`,
`PostbackEventType`, `SettlementStatus`, `SiteStatus`, `SiteSyncStatus`) plus
`final readonly` DTOs: `NetworkAffiliate`, `NetworkConversionDraft`,
`NetworkLegSnapshot`, `NetworkPostedConversion`, `AttributionDecision`,
`CreatorBalanceSummary`, `CreatorPostbackResponse`, `FulfillmentReceipt`,
`FulfillmentResult`, `MerchantStatementSummary`, `NotificationPayload`,
`OfferDiscoveryFilter`, `OfferPerformanceMetrics`, `RestrictionContext`,
`SiteCatalogEndpoint`, `SiteVerificationTarget`.

## Breaking changes from the flat layout

| Before | After |
|---|---|
| `Actions\RecordNetworkConversion::run($link, ...)` | `Features\Ledger\Actions\RecordNetworkConversion::execute(string $offerId, string $affiliateId, ...)` |
| `Services\CreatorBalances::for(...)` | `Features\Ledger\Queries\CreatorBalancesQuery::for(...)` |
| `Services\NetworkLedgerReconciliationService` | `Features\Ledger\Queries\LedgerReconciliationQuery` and `ReconcileOfferLedger` |
| `Enums\{OfferStatus,SiteStatus,…}` | `Data\{OfferStatus,SiteStatus,…}` |
| `Models\*` | `Features\<Feature>\Models\*` |
| `Strategies\*` | `Features\Sites\Extensions\*` implementing `Contracts\SiteVerificationStrategy` |
| `Adapters\Affiliates\*` | `Drivers\Affiliates\*` |
| `Support\*` | `Platform\*` (e.g. `DomainHostValidator`, `QueryParameters`, `ScopesByBelongsToOwner`) |
| `Http\Controllers\*`, `Listeners\*`, `Console\Commands\*` | `Features\<Feature>\EntryPoints\{Http,Listeners,Console}\*` |

There are no aliases or compatibility shims. Update imports directly.
