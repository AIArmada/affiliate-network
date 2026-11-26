---
name: affiliate-network-development
description: Develop and integrate against aiarmada/affiliate-network — a Modular Dune multi-merchant affiliate marketplace with sites, offers, enrollment, links, conversion legs, merchant statements, and outbound creator postbacks. Use when adding a feature, wiring enrollment/attribution/ledger flows, consuming the network APIs, or extending verification strategies and postbacks.
---

# Affiliate Network Development

`aiarmada/affiliate-network` is a standalone multi-merchant affiliate marketplace
built with the **Laravel Dune** modular-package layout. Read the package
`CONTEXT.md` and `docs/06-services.md` for exact signatures; this skill is the
working map.

## Architecture contract

- `src/Platform/` — feature catalog, extension registry, owner scoping, host/URL guards. Never imports `Features/` or `Drivers/`.
- `src/Features/<Name>/` — `Actions/`, `Models/`, `Queries/`, `Api/`, `EntryPoints/`, `Extensions/`, `feature.json`.
- `src/Contracts/` + `src/Data/` — pure interfaces, backed enums, `final readonly` DTOs.
- `src/Drivers/` — contract implementations (affiliates adapters, HTTP, DNS).
- Cross-feature imports are limited to `Features\<Name>\Api\*`.
- Writes go through `Actions`; reads through `Queries`/`Api`. Every `table:*` is owned by exactly one `feature.json` and only its listed writers may mutate it.

Verify boundaries mechanically, not by eyeballing:

```bash
php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network
./vendor/bin/phpstan analyse -c packages/affiliate-network/tools/package-phpstan.neon \
  --autoload-file=packages/affiliate-network/tools/Architecture/PackageOwnedWriteRule.php \
  --no-progress
./vendor/bin/pint packages/affiliate-network
./vendor/bin/pest --parallel tests/src/AffiliateNetwork
```

## Feature catalog

| Feature | Actions | Queries / Api |
|---|---|---|
| `Sites` | `CreateSite`, `RegisterSite`, `UpdateSite`, `VerifySite`, `VerifySiteByStrategy`, `GenerateVerificationToken`, `IssueCatalogToken`, `RotateCatalogToken`, `RejectSite`, `SuspendSite`, `ReinstateSite`, `AdjustMerchantPrepaidBalance`, `RecordSiteSyncStatus`, `DeleteSite` | `SitesApi`, `SiteLookupQuery` |
| `Catalog` | `CreateOffer`, `SubmitOffer`, `UpdateOffer`, `PublishOffer`, `ArchiveOffer`, `DeleteOffer`, `ArchiveExpiredOffers`, `SyncSiteOffers`, `SyncOfferCreatives`, `CreateCategory`, `UpdateCategory`, `DeleteCategory`, `CreateCreative`, `UpdateCreative`, `DeleteCreative` | `CatalogApi`, `OfferDiscoveryQuery`, `OfferMetricsQuery`, `EvaluateOfferRestrictions`, `EvaluateActivePromotion`, `ResolvePublicOfferQuery` |
| `Enrollment` | `ApplyToOffer`, `ApproveApplication`, `RejectApplication`, `RevokeApplication`, `InviteAffiliateToOffer`, `AcceptOfferInvitation`, `RevokeOfferInvitation`, `UpsertOfferDeal`, `RevokeOfferDeal`, `DeleteOfferEnrollments` | `EnrollmentApi`, `ApplicationStatusQuery` |
| `Attribution` | `CreateOfferLink`, `CreateCreativeLink`, `AssignOfferCoupon`, `RevokeOfferCoupon`, `DeactivateOfferLink`, `IncrementLinkClicks`, `RecordLinkCounters`, `RecountLinkCounters`, `DeleteOfferLinks` | `AttributionApi`, `CreativePayloadQuery`, `LinkStatsQuery`, `ResolveOfferLinkQuery`, `ResolveOfferCouponQuery` |
| `Ledger` | `RecordNetworkConversion`, `PostConversionLeg`, `ConfirmConversionLeg`, `SupersedeConversionLeg`, `LockMaturedLegs`, `ReverseConversionLeg`, `AdjustConversionLeg`, `FulfillConversionLeg`, `CheckOfferCaps`, `GenerateMerchantStatement`, `IssueMerchantStatement`, `MarkMerchantStatementPaid`, `VoidMerchantStatement`, `ReconcileOfferLedger`, `RecordOrderAttribution` | `LedgerApi`, `CreatorBalancesQuery`, `LedgerReconciliationQuery`, `MerchantReceivablesQuery`, `ResolveLegOwnerQuery` |
| `Postbacks` | `ConfigureCreatorPostback`, `DeleteCreatorPostback`, `EnqueueCreatorPostbacks`, `DeliverCreatorPostback` | `PostbacksApi` |

## Canonical write patterns

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\{CreateOffer, PublishOffer};
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\{ApplyToOffer, ApproveApplication};
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateOfferLink;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;

$site = app(SitesApi::class)->findById($siteId); // ?SiteRecord
$offer = app(CreateOffer::class)->execute($site, ['name' => 'Summer', 'rate_base_bp' => 1000]);
$offer = app(PublishOffer::class)->execute($offer);

$application = app(ApplyToOffer::class)->execute((string) $offer->getKey(), $affiliateId, 'reason');
app(ApproveApplication::class)->execute($application, $reviewerId);

$link = app(CreateOfferLink::class)->execute((string) $offer->getKey(), $affiliateId, [
    'target_url' => 'https://store.example/product/1',
    'sub_id' => 'blog',
]);

$leg = app(RecordNetworkConversion::class)->execute(
    offerId: (string) $offer->getKey(),
    affiliateId: $affiliateId,
    revenueMinor: 5999,
    currency: 'USD',
    externalReference: 'ORDER-123',
    linkId: (string) $link->getKey(),
);
```

> `CreateOffer`/`SubmitOffer`/`SyncSiteOffers` take a `SiteRecord` (from `SitesApi`), not an `AffiliateSite` model. `RecordNetworkConversion`/`PostConversionLeg` take scalar IDs, not a link model.

## The nine power features

1. **Locking window** — set `offer.locking_days` or `site.default_locking_days`; `LockMaturedLegs::execute()` matures `pending`→`posted`.
2. **Statements + prepaid/spend caps** — `GenerateMerchantStatement` → `IssueMerchantStatement` → `MarkMerchantStatementPaid`/`VoidMerchantStatement`; `CheckOfferCaps` enforces site balance/caps.
3. **Private/unlisted invitations + deals** — `InviteAffiliateToOffer`, `AcceptOfferInvitation`, `UpsertOfferDeal`.
4. **Linkless coupons** — `AssignOfferCoupon`, `AttributionApi::resolveCouponForSite`, pass `couponCode` to conversions.
5. **Outbound creator postbacks + sub IDs** — `ConfigureCreatorPostback`, `EnqueueCreatorPostbacks`; `{sub1}`/`{sub2}`/`{sub3}` macros and HMAC-SHA256 signatures.
6. **Caps + fallback redirects** — conversion/payout caps and `offer.fallback_url`/`fallback_offer_id`/`links.default_fallback_url`.
7. **Adjustments + multi-event CPA** — `AdjustConversionLeg` and `offer.event_rates` keyed by `event_type`.
8. **Discovery + metrics** — `OfferDiscoveryQuery::execute(new OfferDiscoveryFilter(...))`, `OfferMetricsQuery::forOffer($record)`.
9. **Restrictions + promotions** — `offer.restrictions` + `offer.active_promotions` evaluated at posting.

## Owner-scoping rules

- `AffiliateSite`, `AffiliateOfferCategory`, `MerchantStatement` → `HasOwner` (direct).
- `AffiliateOffer`, `AffiliateOfferCreative`, `AffiliateOfferInvitation`, `AffiliateOfferDeal`, `AffiliateOfferCoupon`, `AffiliateOfferApplication` → `ScopesByBelongsToOwner` via `site`/`offer.site`.
- `AffiliateOfferLink`, `NetworkConversionLeg` → via `affiliate`, falling back to `offer.site`.
- Re-enter the record owner for cross-tenant flows:
  ```php
  OwnerContext::withOwner($owner, fn () => $action->execute(...));
  ```
- Never use `withoutOwnerScope()` outside explicit admin/system surfaces; use `OwnerContext::withOwner(null, ...)` for deliberate global work.
- Combined engine + owner installs require a merchant `Affiliate` row for affiliate writes (`Exceptions\AffiliateProfileRequired`).

## Extension slots

- `site.verification_strategy` → implement `Contracts\SiteVerificationStrategy` (`methodKey`, `label`, `verify`, `getInstructions`), place it in `Features/Sites/Extensions/`, register in `feature.json`.
- Identity: bind `Contracts\AffiliateIdentityResolver` (engine adapter or `Drivers\UserKeyIdentityDriver`).
- Ledger/fulfillment: `Contracts\NetworkLedger`, `Contracts\Fulfillment`.
- External HTTP/DNS: `Contracts\CatalogReader`, `DnsResolver`, `SiteContentClient`, `CreatorPostbackTransport`.

## Webhook / postback contracts

Merchant → network (enabled by `postbacks.enabled`):

- `POST {prefix}/conversions` — `site`, one of `link_code`/`coupon_code`, `external_reference`, `revenue_minor`, `currency?`, `event_type?`, `sub_id`/`sub_id_2`/`sub_id_3`, `country?`, `channel?`, `device?`, `engine_touch_at` (present, nullable), `network_clicked_at`.
- `POST {prefix}/conversions/void` — adds `reason`; idempotent reversal.
- `POST {prefix}/conversions/adjust` — `adjustment_reference` plus `delta_revenue_minor` or `new_revenue_minor`.
- Auth: bearer catalog token (`IssueCatalogToken`/`RotateCatalogToken`), constant-time check via `SitesApi::matchesCatalogToken`.

Network → creator:

- `ConfigureCreatorPostback($affiliateId, [...])` stores a URL template, method, events, and encrypted signing secret; a supplied offer must resolve to a real offer.
- Delivery signs `X-Affiliate-Network-Signature: hash_hmac('sha256', $resolvedUrl, $secret)`.
- Delivery claims an `attempt_token`/`lease_expires_at` before the transport call and finalizes only while the lease is current. Retries are at-least-once, not external exactly-once.
- Transport validates through `commerce-support`'s `PublicHttpUrlGuard` (public HTTP(S) only, pinned, no redirects, bounded body).

## Reliability rules

- Conversions are idempotent on `(link|coupon, reference, event_type)`; replays return the existing leg and fire no duplicate event.
- Currency mismatch counts the conversion but zeroes commission/fee/payout; the leg keeps reported revenue for audit.
- Only `LegStatus::Posted` pays (`$leg->paysOut()`); `pending` awaits locking, `superseded` lost the decider, `reversed` was refunded.
- Statements only claim posted, unstated, non-companion legs.
- Never add `InteractsWithSockets` or mutable state to `final readonly` events.

## Definition of done

- `package-check.php` 0 violations, PHPStan 0 errors, scoped Pint clean, AffiliateNetwork Pest suite green.
- Update the owning package docs (`docs/*.md`) and `CONTEXT.md` in the same change.
- Refresh `resources/boost/` when the public API changes.
