---
title: Models
---

# Models Reference

All thirteen models live under `AIArmada\AffiliateNetwork\Features\<Feature>\Models`.
Every model uses `HasUuids`, `HasCommerceAudit`, `LogsCommerceActivity`, and a
config-backed `getTable()` override reading
`affiliate-network.database.tables.*` (with the configured prefix as fallback).
There is no literal `$table` property on any model.

## Morph map aliases

The service provider registers short morph aliases:

| Alias | Model |
|---|---|
| `affiliate_site` | `AffiliateSite` |
| `affiliate_offer_category` | `AffiliateOfferCategory` |
| `affiliate_offer` | `AffiliateOffer` |
| `affiliate_offer_creative` | `AffiliateOfferCreative` |
| `affiliate_offer_application` | `AffiliateOfferApplication` |
| `affiliate_offer_invitation` | `AffiliateOfferInvitation` |
| `affiliate_offer_deal` | `AffiliateOfferDeal` |
| `affiliate_offer_link` | `AffiliateOfferLink` |
| `affiliate_offer_coupon` | `AffiliateOfferCoupon` |
| `network_conversion_leg` | `NetworkConversionLeg` |
| `merchant_statement` | `MerchantStatement` |
| `creator_postback_endpoint` | `CreatorPostbackEndpoint` |
| `creator_postback_delivery` | `CreatorPostbackDelivery` |

## Owner scoping summary

| Model | Trait | Owner path |
|---|---|---|
| `AffiliateSite` | `HasOwner` | direct (`owner_type`/`owner_id`) |
| `AffiliateOfferCategory` | `HasOwner` | direct |
| `MerchantStatement` | `HasOwner` | direct |
| `AffiliateOffer` | `ScopesByBelongsToOwner` | `site` |
| `AffiliateOfferCreative` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferInvitation` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferDeal` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferCoupon` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferApplication` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferLink` | `ScopesByBelongsToOwner` | `affiliate`, fallback `offer.site` |
| `NetworkConversionLeg` | `ScopesByBelongsToOwner` | `affiliate`, fallback `offer.site` |
| `CreatorPostbackEndpoint` | none | affiliate-scoped by query, not a global scope |
| `CreatorPostbackDelivery` | none | reached through its endpoint |

> **note**
> `AffiliateOfferApplication` scopes through `offer.site` (the merchant owns the
> offer), not the affiliate. This keeps merchant-side review and creator-side
> read models consistent.

---

## AffiliateSite

`AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite`

| Property | Type | Notes |
|---|---|---|
| `id` | string | UUID |
| `owner_type` / `owner_id` | string\|null | Direct owner morph |
| `name` | string | Display name |
| `domain` | string | Unique, normalized lowercase hostname |
| `description` | string\|null | |
| `status` | `SiteStatus` | `pending`, `verified`, `suspended`, `rejected` |
| `verification_method` | string\|null | `dns`, `meta_tag`, `file` |
| `verification_token` | string\|null | |
| `verified_at` | `CarbonImmutable`\|null | |
| `catalog_url` | string\|null | Empty = owned/shared-DB site |
| `catalog_token_encrypted` | string\|null | Encrypted catalog/postback token |
| `catalog_token_issued_at` | `CarbonImmutable`\|null | |
| `sync_status` | `SiteSyncStatus` | `never`, `ok`, `partial`, `failed` |
| `last_synced_at` | `CarbonImmutable`\|null | |
| `default_locking_days` | int | 0 = no window |
| `prepaid_balance_minor` | int\|null | null = unlimited prepaid |
| `budget_currency` | string\|null | |
| `monthly_spend_cap_minor` | int\|null | |
| `require_settled_for_payout` | bool | Pay-when-paid gate |
| `settings` / `metadata` | array\|null | |

Relations: `owner()` (MorphTo), `offers()` (HasMany `AffiliateOffer`).

Methods: `isVerified()`, `isPending()`, `isSuspended()`, `isRejected()`,
`hasCatalogToken()`, `matchesCatalogToken(?string $token): bool`
(constant-time, fail-closed), `toRecord(): SiteRecord`.

Deleting a site cascades in application code through `DeleteSiteDependents`.

---

## AffiliateOffer

`AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer`

| Group | Properties |
|---|---|
| Identity | `id`, `site_id`, `category_id`, `name`, `slug`, `description`, `terms` |
| Lifecycle | `status` (`OfferStatus`), `visibility` (`OfferVisibility`), `source` (`OfferSource`), `published_at`, `archived_at`, `starts_at`, `ends_at` |
| Pricing | `rate_base_bp`, `rate_fixed_minor`, `currency`, `cookie_days`, `volume_tiers`, `event_rates`, `active_promotions`, `network_fee_bp` |
| Marketplace | `is_featured`, `requires_approval`, `landing_url`, `fallback_url`, `fallback_offer_id`, `allow_deep_linking` |
| Limits | `locking_days`, `daily_conversion_cap`, `monthly_conversion_cap`, `total_conversion_cap`, `per_affiliate_conversion_cap`, `monthly_payout_cap_minor`, `total_payout_cap_minor` |
| Restrictions | `restrictions`, `metadata` |
| Catalog provenance | `external_program_id`, `subject_type`, `subject_key`, `source_url`, `source_checksum`, `creatives_checksum`, `last_synced_at` |

Relations: `site()`, `category()`, `creatives()`, `applications()`, `links()`,
`legs()`, `invitations()`, `deals()`, `coupons()`.

Methods: `isDraft()`, `isActive()`, `wasActiveAt(CarbonImmutable $at)`,
`isFixed()`, `formattedRate()`, `toRecord(): OfferRecord`.
Statics: `rateBlockColumns(): array`, `normalizeVolumeTiers(?array $tiers, ?string $currency): ?array`.
Scope: `whereSiteVerified()`.

Deleting an offer cascades through `DeleteOfferDependents`.

---

## AffiliateOfferCategory

`AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory`

Properties: `id`, `owner_type`, `owner_id`, `parent_id`, `name`, `slug`,
`description`, `icon`, `sort_order` (int), `is_active` (bool).

Relations: `owner()` (MorphTo), `parent()`, `children()`, `offers()`.

`getTable()` honors a per-instance alias (`$this->table`) so self-join queries
bind correctly. Unique per `(owner_type, owner_id, slug)`.

Deleting cascades through `DeleteCategoryDependents`.

---

## AffiliateOfferCreative

`AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative`

Implements `Spatie\MediaLibrary\HasMedia` via `InteractsWithMedia`.

Properties: `id`, `offer_id`, `type` (`CreativeType`), `name`, `description`,
`external_creative_id`, `source_asset_url`, `destination_url`, `width`,
`height`, `html_code`, `is_active`, `sort_order`, `metadata`.

Relations: `offer()`.

Methods: `registerMediaCollections()` (single-file `creative_asset` on the
`public` disk), `getAssetUrl(): ?string`, `toRecord(): CreativeRecord`.

`getAssetUrl()` returns `source_asset_url` for imported rows and the media URL
for manual rows.

---

## AffiliateOfferApplication

`AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication`

Properties: `id`, `offer_id`, `affiliate_id`, `invitation_id`, `status`
(`ApplicationStatus`), `reason`, `rejection_reason`, `reviewed_by`,
`reviewed_at`, `approved_at`, `rejected_at`, `revoked_at`, `metadata`.

Relations: `offer()`. Affiliate identity is resolved through `AffiliateIdentityResolver` using `affiliate_id`, not an Eloquent relationship.

Methods: `isPending()`, `isApproved()`, `toRecord(): ApplicationRecord`.

Unique per `(offer_id, affiliate_id)`.

---

## AffiliateOfferInvitation

`AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation`

Properties: `id`, `offer_id`, `affiliate_id`, `token` (unique, 40 chars),
`invited_by`, `auto_approve` (bool), `expires_at`, `accepted_at`, `revoked_at`,
`metadata`.

Relations: `offer()`.

Methods: `isActive()`, `toRecord(): InvitationRecord`.

---

## AffiliateOfferDeal

`AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal`

Properties: `id`, `offer_id`, `affiliate_id`, `rate_base_bp`,
`rate_fixed_minor`, `network_fee_bp`, `currency`, `volume_tiers`, `starts_at`,
`ends_at`, `is_active`, `notes`.

Relations: `offer()`.

Methods: `isActiveAt(CarbonImmutable $at)`, `toOverride(): DealRateOverride`.

---

## AffiliateOfferCoupon

`AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon`

Properties: `id`, `offer_id`, `site_id`, `affiliate_id`, `code` (uppercase,
unique per site), `is_active`, `starts_at`, `ends_at`, `metadata`.

Relations: `offer()`.

Methods: `isActiveAt(CarbonImmutable $at)`, `toRecord(): OfferCouponRecord`.

---

## AffiliateOfferLink

`AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink`

Properties: `id`, `link_id` (backing `AIArmada\Links\Models\Link`), `offer_id`,
`affiliate_id`, `site_id`, `sub_id`, `sub_id_2`, `sub_id_3`, `clicks`,
`conversions`, `revenue` (minor), `currency`, `is_active`, `metadata`.

Relations: `link()`, `offer()`, `site()`. Affiliate identity is resolved by `AffiliateIdentityResolver`.

Methods: `isExpired()`, `trackedSlug(): ?string`, `toRecord(): OfferLinkRecord`.

Counters are written only through Attribution feature actions/APIs, never from
this model directly.

---

## NetworkConversionLeg

`AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg`

Append-only money leg.

| Group | Properties |
|---|---|
| Identity | `id`, `link_id`, `offer_id`, `site_id`, `affiliate_id`, `link_code`, `idempotency_key` (unique), `parent_leg_id` |
| Money | `revenue_minor`, `revenue_currency`, `commission_minor`, `commission_currency`, `fee_minor`, `fee_bp`, `payout_minor`, `tier_rate_bp`, `tier_min_volume_minor` |
| Attribution | `external_reference`, `event_type`, `coupon_code`, `sub_id`, `sub_id_2`, `sub_id_3`, `metadata` |
| Lifecycle | `status` (`LegStatus`), `statement_id`, `locks_at`, `locked_at`, `fulfilled_at`, `occurred_at` |

Relations: `offer()`, `site()`. Affiliate identity is resolved by `AffiliateIdentityResolver`.

Methods: `paysOut(): bool`, `toSnapshot(): NetworkLegSnapshot`.

`parent_leg_id` marks adjustment and reversal companions. Companions never pay;
`CreatorBalancesQuery` treats a reversal companion of a fulfilled leg as a
clawback.

---

## MerchantStatement

`AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement`

Properties: `id`, `owner_type`, `owner_id`, `site_id`, `statement_number`
(unique), `status` (`SettlementStatus`), `currency`, `period_start`,
`period_end`, `leg_count`, `gross_revenue_minor`, `commission_minor`,
`fee_minor`, `payout_minor`, `issued_at`, `due_at`, `paid_at`,
`payment_reference`, `metadata`.

Relations: `site()`, `legs()` (HasMany `NetworkConversionLeg` via `statement_id`).

Methods: `toSummary(): MerchantStatementSummary`.

---

## CreatorPostbackEndpoint

`AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint`

Properties: `id`, `affiliate_id`, `offer_id` (null = all offers),
`url_template`, `http_method` (`GET`/`POST`), `events` (array of
`PostbackEventType` values), `signing_secret_encrypted`, `is_active`.

Methods: `subscribesTo(PostbackEventType)`, `eventTypes(): list<PostbackEventType>`,
`toRecord(): CreatorPostbackRecord`.

---

## CreatorPostbackDelivery

`AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery`

Properties: `id`, `endpoint_id`, `leg_id`, `event_type`, `resolved_url`,
`status` (`PostbackDeliveryStatus`), `http_status`, `attempts`, `last_error`,
`delivered_at`.

Relations: `endpoint()`.

Unique per `(endpoint_id, leg_id, event_type)` so redelivery is idempotent.
