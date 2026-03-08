---
title: Multi-Tenancy
---

# Multi-Tenancy

The package supports full multi-tenant operation via the `commerce-support` owner scoping system.

## Enabling Multi-Tenancy

```env
AFFILIATE_NETWORK_OWNER_ENABLED=true
AFFILIATE_NETWORK_OWNER_INCLUDE_GLOBAL=false
AFFILIATE_NETWORK_OWNER_AUTO_ASSIGN=true
```

Or in config:

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

## Owner-Scoped Models

When enabled, these models are automatically scoped:

| Model | Scoping Method |
|-------|----------------|
| `AffiliateSite` | Direct owner (`HasOwner`) |
| `AffiliateOfferCategory` | Direct owner (`HasOwner`) |
| `AffiliateOffer` | Via site (`ScopesBySiteOwner`) |
| `AffiliateOfferApplication` | Via affiliate (`ScopesByAffiliateOwner`) |
| `AffiliateOfferLink` | Via affiliate (`ScopesByAffiliateOwner`) |

## Direct Owner Scoping

Models with `HasOwner` trait have `owner_type` and `owner_id` columns:

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

// Automatically scoped to current owner
$sites = AffiliateSite::all();

// Query specific owner
$sites = AffiliateSite::forOwner($merchant)->get();

// Include global records (owner_id = null)
$sites = AffiliateSite::forOwner($merchant, includeGlobal: true)->get();

// Global records only
$sites = AffiliateSite::globalOnly()->get();
```

## Relationship-Based Scoping

### ScopesBySiteOwner

For models belonging to a site (e.g., `AffiliateOffer`):

```php
// Offers are automatically scoped via their site's owner
$offers = AffiliateOffer::all();

// This enforces:
// - site.owner_type = current_owner_type
// - site.owner_id = current_owner_id
```

Cross-tenant validation is enforced on create/update:

```php
// This throws RuntimeException if site belongs to different owner
$offer = AffiliateOffer::create([
    'site_id' => $otherOwnerSite->id, // ❌ Blocked
    'name' => 'Test Offer',
]);
```

### ScopesByAffiliateOwner

For models belonging to an affiliate:

```php
// Applications are automatically scoped via their affiliate's owner
$applications = AffiliateOfferApplication::all();

// This enforces:
// - affiliate.owner_type = current_owner_type
// - affiliate.owner_id = current_owner_id
```

## Global Records

When `include_global` is `true`, queries include records with `owner_id = null`:

```php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Include global records
],
```

Use cases for global records:
- System-wide offer categories
- Platform-level sites
- Shared creatives

## Auto-Assignment

When `auto_assign_on_create` is `true`, new records automatically get the current owner:

```php
// Current owner is automatically assigned
$site = AffiliateSite::create([
    'name' => 'My Store',
    'domain' => 'mystore.com',
]);

// $site->owner_type and $site->owner_id are set automatically
```

## Bypassing Owner Scope

For system/admin operations:

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

// Bypass owner scope
$allSites = AffiliateSite::withoutGlobalScope('owner')->get();

// Or use withoutGlobalScopes()
$allSites = AffiliateSite::withoutGlobalScopes()->get();
```

## Owner Resolver

The package uses `OwnerResolverInterface` from commerce-support:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$resolver = app(OwnerResolverInterface::class);
$owner = $resolver->resolve();
```

Bind your implementation in a service provider:

```php
$this->app->bind(
    OwnerResolverInterface::class,
    TenantOwnerResolver::class
);
```

## Cross-Tenant Protection

The scoping traits include runtime validation:

```php
// Creating application for affiliate from different tenant
try {
    $application = AffiliateOfferApplication::create([
        'offer_id' => $offer->id,
        'affiliate_id' => $otherTenantAffiliate->id, // Different owner
    ]);
} catch (RuntimeException $e) {
    // "Cannot create record for an affiliate owned by a different owner."
}
```

## Filament Integration

The Filament plugin respects owner scoping:

```php
// Resources automatically scope queries
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        // Owner scope already applied via model traits
        ->with(['site', 'category']);
}
```

Ensure your Filament panel's tenancy aligns with the owner resolver.
