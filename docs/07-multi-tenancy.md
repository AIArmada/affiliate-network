---
title: Multi-Tenancy
---

# Multi-Tenancy

Owner scoping is provided by `aiarmada/commerce-support` and enabled through
`affiliate-network.owner`.

```env
AFFILIATE_NETWORK_OWNER_ENABLED=true
AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL=false
```

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

## Owner-scoped models

| Model | Trait | Owner path |
|---|---|---|
| `AffiliateSite` | `HasOwner` + `HasOwnerScopeConfig` | direct (`owner_type`/`owner_id`) |
| `AffiliateOfferCategory` | `HasOwner` + `HasOwnerScopeConfig` | direct |
| `MerchantStatement` | `HasOwner` + `HasOwnerScopeConfig` | direct |
| `AffiliateOffer` | `ScopesByBelongsToOwner` | `site` |
| `AffiliateOfferCreative` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferInvitation` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferDeal` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferCoupon` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferApplication` | `ScopesByBelongsToOwner` | `offer.site` |
| `AffiliateOfferLink` | `ScopesByBelongsToOwner` | `affiliate`, fallback `offer.site` |
| `NetworkConversionLeg` | `ScopesByBelongsToOwner` | `affiliate`, fallback `offer.site` |

`CreatorPostbackEndpoint` and `CreatorPostbackDelivery` are affiliate-scoped by
query (their `affiliate_id` is the owner boundary) and do not add a global
scope.

## `ScopesByBelongsToOwner`

The trait is the only relationship-based owner scope in the package. It
delegates the actual predicate to commerce-support's `OwnerScope` and adds
relationship traversal.

```php
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;

final class AffiliateOffer extends Model
{
    use ScopesByBelongsToOwner;

    protected static function ownerViaRelation(): string
    {
        return 'site';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
    }
}
```

Key behavior:

- **`ownerViaRelation()`** is the primary relation (may be dotted, e.g.
  `offer.site`). The last segment must be a model using `HasOwner`.
- **`ownerFallbackRelation()`** lets an affiliate-owned model fall back to the
  offer's site when the affiliate identity is unavailable (used by
  `AffiliateOfferLink` and `NetworkConversionLeg`).
- **`effectiveOwnerRelation(Model $model)`** picks the primary relation when its
  terminal model uses `HasOwner`, otherwise the fallback, otherwise `null`
  (unscoped). This is what the global scope and write assertions use.
- **Create/update/delete assertions** (`assertOwnerRelationAccessible`) re-read
  the owner relation inside the configured `OwnerScope` and throw when the
  relation is missing or owned by someone else. The message interpolates the
  model class:
  `Cannot create, update, or delete {ModelClass} without an accessible owner relation.`
- If the relation does not start with a belongs-to, it throws
  `{ModelClass}::ownerViaRelation() must begin with a belongs-to relation.`

Reads:

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;

$offers = AffiliateOffer::all();                          // scoped via offer.site
$offers = AffiliateOffer::forOwner($merchant)->get();     // explicit owner
$offers = AffiliateOffer::forOwner($merchant, includeGlobal: true)->get();
$offers = AffiliateOffer::globalOnly()->get();            // owner null only
$offers = AffiliateOffer::withoutOwnerScope()->get();     // deliberate bypass
```

## Cross-owner writes re-enter the record's owner

Network writes routinely originate outside the owning merchant's request
(postbacks, locked-leg maturation, prepaid adjustments). Each action resolves
the record's owner and performs the write inside `OwnerContext::withOwner(...)`
instead of trusting ambient state.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($owner, function (): void {
    AffiliateSite::create([
        'name' => 'My Store',
        'domain' => 'mystore.com',
        // owner_type/owner_id are filled by HasOwner
    ]);
});
```

| Flow | Owner resolution |
|---|---|
| `ApplyToOffer` | site owner (`OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId)`) |
| `RecordNetworkConversion` | affiliate identity's owner (fallback: null) |
| `AdjustConversionLeg`, `LockMaturedLegs` | `ResolveLegOwnerQuery::forLeg($leg)` |
| `GenerateMerchantStatement`, `VoidMerchantStatement` | site owner |
| `MarkMerchantStatementPaid` | statement's own row (already owner scoped) |
| `AdjustMerchantPrepaidBalance` | site owner |
| `RegisterSite` | the passed `$owner` |

`ResolveLegOwnerQuery` centralises leg ownership: it prefers the affiliate
identity's owner and falls back to the offer's site owner, so reversal and
adjustment flows work even when the ambient request owner is a different
merchant or creator.

## Affiliate profiles in combined installs

When the Affiliates identity adapter is bound and either
`affiliate-network.owner.enabled` or `affiliates.owner.enabled` is true,
affiliate-facing writes require a merchant `Affiliate` row.
`ApplyToOffer`, `CreateOfferLink`, `CreateCreativeLink`,
`AssignOfferCoupon`, and conversion posting throw
`Exceptions\AffiliateProfileRequired` before relationship owner validation can
fail.

`AffiliateIdentityResolver` exposes the contract:

```php
$resolver->requiresMerchantProfile();      // bool
$resolver->hasMerchantProfile($affiliateId); // bool
```

`UserKeyIdentityDriver` returns `false` from `requiresMerchantProfile()`, and
the identity driver still falls back to the host user for display/reporting
reads. Standalone installs therefore keep working without affiliate records.

## Standalone identity: `UserKeyIdentityDriver`

When the engine is absent, the package binds `UserKeyIdentityDriver`. It:

- Resolves the host user as the affiliate identity.
- Reads `handle` when present, else assigns a stable `creator-{hash}` handle.
- Reports `ownerType`/`ownerId` from the user's owner relation when available.
- Returns `false` from `requiresMerchantProfile()`.

Issued links snapshot the handle, so renaming a user never changes existing
URLs.

## Global records

Global rows have `owner_type` and `owner_id` both null. They are global-*only*,
not "owned by everyone": owner-scoped queries skip them unless
`include_global` is true.

```php
'affiliate-network' => [
    'owner' => [
        'enabled' => true,
        'include_global' => true,
    ],
],
```

`owner = null` in `OwnerContext::withOwner(null, ...)` means global context, not
"all owners". Use it only for intentional system/admin work and pair it with an
explicit `withoutOwnerScope()`.

## Owner resolver

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$owner = app(OwnerResolverInterface::class)->resolve();
```

Bind your resolver in a service provider:

```php
$this->app->bind(OwnerResolverInterface::class, TenantOwnerResolver::class);
```

## Filament

Filament resources return owner-safe queries from `getEloquentQuery()` and
revalidate submitted IDs inside action handlers. Network-wide admin surfaces
(merchant statements, network stats) intentionally call `withoutOwnerScope()`
and are gated behind the configured admin ability instead. See
[`filament-affiliate-network`](../../filament-affiliate-network/docs/04-usage.md).

## Verification

- Add at least one cross-tenant regression test proving reads are isolated and
  writes throw or abort.
- Reuse `commerce-support`'s `OwnerScopingContractTests` where practical.
- Grep for deliberate bypasses and confirm each is intentional:
  `rg -n "withoutOwnerScope\(|withoutGlobalScope\(ScopesByBelongsToOwner" packages/affiliate-network/src`.
