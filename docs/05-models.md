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

### Relationships

```php
$site->owner;   // MorphTo - Owner model
$site->offers;  // HasMany - AffiliateOffer
```

### Scopes & Methods

```php
$site->isVerified();  // bool
$site->isPending();   // bool
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
| `status` | `string` | draft, pending, active, paused, expired, rejected |
| `commission_type` | `string` | percentage, fixed |
| `commission_rate` | `int` | Commission in basis points or minor units |
| `currency` | `string\|null` | Currency code (e.g., USD) |
| `cookie_days` | `int\|null` | Cookie duration |
| `is_featured` | `bool` | Featured in marketplace |
| `is_public` | `bool` | Visible in marketplace |
| `requires_approval` | `bool` | Requires affiliate approval |
| `landing_url` | `string\|null` | Default landing page |
| `restrictions` | `array\|null` | Traffic restrictions |
| `metadata` | `array\|null` | Custom metadata |
| `starts_at` | `CarbonImmutable\|null` | Campaign start |
| `ends_at` | `CarbonImmutable\|null` | Campaign end |

### Relationships

```php
$offer->site;         // BelongsTo - AffiliateSite
$offer->category;     // BelongsTo - AffiliateOfferCategory
$offer->creatives;    // HasMany - AffiliateOfferCreative
$offer->applications; // HasMany - AffiliateOfferApplication
$offer->links;        // HasMany - AffiliateOfferLink
```

### Methods

```php
$offer->isActive();  // Checks status and date range
```

### Traits

- `ScopesBySiteOwner` - Owner scoping via site relationship
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

- `ScopesByAffiliateOwner` - Owner scoping via affiliate relationship
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
| `code` | `string` | Unique tracking code |
| `target_url` | `string` | Destination URL |
| `custom_parameters` | `string\|null` | Custom URL params |
| `sub_id` | `string\|null` | Sub-tracking ID 1 |
| `sub_id_2` | `string\|null` | Sub-tracking ID 2 |
| `sub_id_3` | `string\|null` | Sub-tracking ID 3 |
| `clicks` | `int` | Click count |
| `conversions` | `int` | Conversion count |
| `revenue` | `int` | Total revenue (minor units) |
| `is_active` | `bool` | Active status |
| `expires_at` | `CarbonImmutable\|null` | Expiration date |
| `metadata` | `array\|null` | Custom metadata |

### Relationships

```php
$link->offer;     // BelongsTo - AffiliateOffer
$link->affiliate; // BelongsTo - Affiliate
$link->site;      // BelongsTo - AffiliateSite
```

### Methods

```php
$link->incrementClicks();           // Increment click counter
$link->recordConversion($revenue);  // Record conversion
$link->isExpired();                 // Check if expired
AffiliateOfferLink::generateCode(); // Generate unique code
```

### Traits

- `ScopesByAffiliateOwner` - Owner scoping via affiliate relationship
- `HasUuids` - UUID primary keys
