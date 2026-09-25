---
title: Models
---

# Models Reference

## AffiliateSite

Represents a merchant's website/domain in the network.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `owner_type` | `string\|null` | Morph type for multi-tenancy |
| `owner_id` | `string\|null` | Morph ID for multi-tenancy |
| `name` | `string` | Site display name |
| `domain` | `string` | Domain (unique) |
| `description` | `string\|null` | Site description |
| `status` | `string` | pending, verified, suspended, rejected |
| `verification_method` | `string\|null` | dns, meta_tag, file |
| `verification_token` | `string\|null` | Verification token |
| `verified_at` | `CarbonImmutable\|null` | Verification timestamp |
| `settings` | `array\|null` | Site settings |
| `metadata` | `array\|null` | Custom metadata |
| `catalog_url` | `string\|null` | Merchant catalog base URL (empty = owned site) |
| `catalog_token_encrypted` | `string\|null` | Encrypted catalog/postback token |
| `catalog_token_issued_at` | `CarbonImmutable\|null` | Token issuance timestamp |
| `sync_status` | `string\|null` | Last catalog sync outcome |
| `last_synced_at` | `CarbonImmutable\|null` | Last catalog sync timestamp |

### Relationships

```php
$site->owner;   // MorphTo - Owner model
$site->offers;  // HasMany - AffiliateOffer
```

### Scopes & Methods

```php
$site->isVerified();          // bool
$site->isPending();           // bool
$site->issueCatalogToken();   // string (plaintext, shown once)
$site->rotateCatalogToken();  // string (previous token dies immediately)
$site->hasCatalogToken();     // bool
```

### Traits

- `HasOwner` - Multi-tenancy owner relationship
- `HasOwnerScopeConfig` - Config-based owner scoping
- `HasUuids` - UUID primary keys

---

## AffiliateOffer

Represents an affiliate offer/campaign.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `site_id` | `string` | Foreign key to site |
| `category_id` | `string\|null` | Foreign key to category |
| `name` | `string` | Offer name |
| `slug` | `string` | URL slug (unique per site) |
| `description` | `string\|null` | Offer description |
| `terms` | `string\|null` | Terms and conditions |
| `status` | `OfferStatus` | draft, published, archived |
| `source` | `string` | `synced` (importer owns rates) or `manual` (operator override; sync holds rates back) |
| `network_fee_bp` | `int\|null` | Marketplace take-rate in bp (null = configured default) |
| `rate_base_bp` | `int` | Base percentage in basis points (1000 = 10%), null when fixed-only |
| `rate_fixed_minor` | `int` | Fixed payout in minor units, null when percentage-based |
| `volume_tiers` | `array` | Volume bonus tiers (`min_volume_minor`, `rate_bp`, `currency?`) |
| `active_promotions` | `array` | Active promotions (`id`, `name`, `ends_at`) |
| `currency` | `string\|null` | Currency code (e.g., USD) |
| `cookie_days` | `int\|null` | Cookie duration |
| `is_featured` | `bool` | Featured in marketplace |
| `visibility` | `OfferVisibility` | public, private, unlisted |
| `requires_approval` | `bool` | Requires affiliate approval |
| `landing_url` | `string\|null` | Default landing page |
| `restrictions` | `array\|null` | Traffic restrictions |
| `metadata` | `array\|null` | Custom metadata |
| `starts_at` | `CarbonImmutable\|null` | Campaign start |
| `ends_at` | `CarbonImmutable\|null` | Campaign end |
| `published_at` | `CarbonImmutable\|null` | Last publish timestamp |
| `archived_at` | `CarbonImmutable\|null` | Last archive timestamp |
| `external_program_id` | `string\|null` | Merchant program id (catalog imports) |
| `subject_type` | `string\|null` | Imported subject type |
| `subject_key` | `string\|null` | Imported subject key |

### Relationships

```php
$offer->site;         // BelongsTo - AffiliateSite
$offer->category;     // BelongsTo - AffiliateOfferCategory
$offer->creatives;    // HasMany - AffiliateOfferCreative
$offer->applications; // HasMany - AffiliateOfferApplication
$offer->links;        // HasMany - AffiliateOfferLink
$offer->legs;         // HasMany - NetworkConversionLeg
```

### Methods

```php
$offer->isActive();  // Checks status and date range
```

### Traits

- `ScopesByBelongsToOwner` - Owner scoping via the `site` relationship
- `HasUuids` - UUID primary keys

---

## AffiliateOfferCategory

Hierarchical category for organizing offers.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `owner_type` | `string\|null` | Morph type for multi-tenancy |
| `owner_id` | `string\|null` | Morph ID for multi-tenancy |
| `parent_id` | `string\|null` | Parent category ID |
| `name` | `string` | Category name |
| `slug` | `string` | URL slug |
| `description` | `string\|null` | Description |
| `icon` | `string\|null` | Icon name |
| `sort_order` | `int` | Display order |
| `is_active` | `bool` | Active status |

### Relationships

```php
$category->owner;    // MorphTo - Owner model
$category->parent;   // BelongsTo - Self
$category->children; // HasMany - Self
$category->offers;   // HasMany - AffiliateOffer
```

### Traits

- `HasOwner` - Multi-tenancy owner relationship
- `HasOwnerScopeConfig` - Config-based owner scoping
- `HasUuids` - UUID primary keys

---

## AffiliateOfferCreative

Banner, text link, or other promotional asset.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `offer_id` | `string` | Foreign key to offer |
| `type` | `string` | banner, text, email, html, video |
| `name` | `string` | Creative name |
| `description` | `string\|null` | Description |
| `url` | `string\|null` | Asset URL |
| `file_path` | `string\|null` | Local file path |
| `width` | `int\|null` | Width in pixels |
| `height` | `int\|null` | Height in pixels |
| `alt_text` | `string\|null` | Alt text |
| `html_code` | `string\|null` | HTML embed code |
| `is_active` | `bool` | Active status |
| `sort_order` | `int` | Display order |
| `metadata` | `array\|null` | Custom metadata |

### Relationships

```php
$creative->offer;  // BelongsTo - AffiliateOffer
```

### Owner scope

`AffiliateOfferCreative` uses `ScopesByBelongsToOwner` through the
`offer.site` path. No creative owner columns or migration are required.

---

## AffiliateOfferApplication

Affiliate's application to promote an offer.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `offer_id` | `string` | Foreign key to offer |
| `affiliate_id` | `string` | Foreign key to affiliate |
| `status` | `string` | pending, approved, rejected, revoked |
| `reason` | `string\|null` | Application reason |
| `rejection_reason` | `string\|null` | Rejection reason |
| `reviewed_by` | `string\|null` | Reviewer ID |
| `reviewed_at` | `CarbonImmutable\|null` | Review timestamp |
| `metadata` | `array\|null` | Custom metadata |

### Relationships

```php
$application->offer;     // BelongsTo - AffiliateOffer
$application->affiliate; // BelongsTo - Affiliate
```

### Methods

```php
$application->isPending();   // bool
$application->isApproved();  // bool
```

### Traits

- `ScopesByBelongsToOwner` - Owner scoping via the `affiliate` relationship
- `HasUuids` - UUID primary keys

---

## AffiliateOfferLink

Tracking link for affiliate promotions.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `offer_id` | `string` | Foreign key to offer |
| `affiliate_id` | `string` | Foreign key to affiliate |
| `site_id` | `string\|null` | Foreign key to site |
| `link_id` | `string\|null` | Backing tracked link (`aiarmada/links`) |
| `sub_id` | `string\|null` | Sub-tracking ID 1 |
| `sub_id_2` | `string\|null` | Sub-tracking ID 2 |
| `sub_id_3` | `string\|null` | Sub-tracking ID 3 |
| `clicks` | `int` | Click count |
| `conversions` | `int` | Conversion count |
| `revenue` | `int` | Total revenue (minor units) |
| `currency` | `string\|null` | Revenue currency (ISO code, inherited from the offer) |
| `is_active` | `bool` | Active status |
| `metadata` | `array\|null` | Custom metadata |

### Relationships

```php
$link->link;      // BelongsTo - Links Link (backing tracked link)
$link->offer;     // BelongsTo - AffiliateOffer
$link->affiliate; // BelongsTo - Affiliate
$link->site;      // BelongsTo - AffiliateSite
```

### Methods

```php
$link->incrementClicks();           // Increment click counter
$link->recordConversion($revenue);  // Record conversion
$link->isExpired();                 // Check if the backing link is expired
$link->trackedSlug();               // Backing link slug (or null)
```

### Traits

- `ScopesByBelongsToOwner` - Owner scoping via the `affiliate` relationship
- `HasUuids` - UUID primary keys

---

## NetworkConversionLeg

One posted money leg per network conversion. Append-only: legs are never
edited except for status finalization and reversal markers. Balances,
counters, and reconciliation derive from these rows.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `link_id` | `string` | Foreign key to offer link |
| `offer_id` | `string` | Foreign key to offer |
| `site_id` | `string\|null` | Foreign key to site |
| `affiliate_id` | `string` | Creator key |
| `link_code` | `string` | Tracked link code snapshot |
| `revenue_minor` | `int` | Order revenue (minor units) |
| `revenue_currency` | `string\|null` | Revenue currency |
| `commission_minor` | `int` | Commission before fee (minor units) |
| `commission_currency` | `string` | Commission currency |
| `fee_minor` | `int` | Network fee carved out (minor units) |
| `fee_bp` | `int` | Fee rate applied (basis points) |
| `payout_minor` | `int` | Commission minus fee (minor units) |
| `tier_rate_bp` | `int\|null` | Volume tier rate applied, if any |
| `tier_min_volume_minor` | `int\|null` | Tier floor cleared, if any |
| `external_reference` | `string` | Merchant order reference (idempotency) |
| `status` | `LegStatus` | provisional, posted, superseded, reversed |
| `metadata` | `array\|null` | Custom metadata (reversals link here) |
| `occurred_at` | `CarbonImmutable\|null` | Conversion timestamp |

### Relationships

```php
$leg->link;   // BelongsTo - AffiliateOfferLink
$leg->offer;  // BelongsTo - AffiliateOffer
$leg->site;   // BelongsTo - AffiliateSite
```

### Traits

- `ScopesByBelongsToOwner` - Owner scoping via the `affiliate` relationship
- `HasUuids` - UUID primary keys
