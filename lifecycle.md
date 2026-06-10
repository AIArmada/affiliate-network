# Affiliate Network — Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

The package has **6 tables**, **6 models**, **0 enums**, and **0 state classes**. Two tables
(offers and applications) have meaningful lifecycles with `status` columns. The remaining four
tables (sites, categories, creatives, links) are admin configuration entities where `is_active`
booleans are appropriate. One model (`AffiliateOfferLink`) is missing audit traits.

**Key violations of the lifecycle principle:**

| Problem | Tables Affected |
|---|---|
| Status columns without corresponding `*_at` timestamps | offers, offer_applications |
| Offer lifecycle overcomplicated (6 states → 3) | offers |
| Missing audit trait | offer_links |

**What stays the same:**
- `is_active` booleans on categories, creatives, links (config toggles)
- `is_featured` boolean on offers (curation flag)
- `visibility` string on offers (display concern)
- `status` string on sites (config toggle, no timestamps needed)

---

## 2. Full Inventory by Table

### 2.1 `affiliate_network_sites`

| Column | Type | Current Role | Problem |
|---|---|---|---|
| `id` | uuid | PK | — |
| `owner_type` / `owner_id` | nullableMorphs | Tenancy | — |
| `name` | string | Identity | — |
| `domain` | string (unique) | Identity | — |
| `description` | text (nullable) | Metadata | — |
| `status` | string (default `pending`) | Config state | OK as-is — config toggle, no lifecycle timestamps needed |
| `verification_method` | string (nullable) | Verification flow | — |
| `verification_token` | string (nullable) | Verification flow | — |
| `verified_at` | timestampTz (nullable) | Verification timestamp | OK — keep |
| `settings` | json (nullable) | Configuration | — |
| `metadata` | json (nullable) | Extensibility | — |
| `created_at` | timestampTz | — | — |
| `updated_at` | timestampTz | — | — |

**Sites are config-like entities.** The existing `status` column with `verified_at` timestamp is
sufficient. No additional lifecycle timestamps needed.

**Model:** `AffiliateSite` — uses `HasOwner`, `HasCommerceAudit`, `LogsCommerceActivity`.

---

### 2.2 `affiliate_network_offer_categories`

| Column | Type | Current Role | Problem |
|---|---|---|---|
| `id` | uuid | PK | — |
| `owner_type` / `owner_id` | nullableMorphs | Tenancy | — |
| `parent_id` | foreignUuid (nullable) | Hierarchy | — |
| `name` | string | Identity | — |
| `slug` | string (unique) | Routing | — |
| `description` | text (nullable) | Metadata | — |
| `icon` | string (nullable) | Display | — |
| `sort_order` | unsignedInteger (default 0) | Ordering | — |
| `is_active` | boolean (default true) | Config toggle | OK — admin config toggle, keep boolean |
| `created_at` | timestampTz | — | — |
| `updated_at` | timestampTz | — | — |

**Categories are admin configuration entities.** `is_active` boolean is appropriate. No status
column or timestamps needed.

**Model:** `AffiliateOfferCategory` — uses `HasOwner`, `HasCommerceAudit`, `LogsCommerceActivity`.

---

### 2.3 `affiliate_network_offers`

| Column | Type | Current Role | Problem |
|---|---|---|---|
| `id` | uuid | PK | — |
| `site_id` | foreignUuid | Parent (site) | — |
| `category_id` | foreignUuid (nullable) | Parent (category) | — |
| `name` | string | Identity | — |
| `slug` | string | Routing | — |
| `description` | text (nullable) | Metadata | — |
| `terms` | text (nullable) | Legal | — |
| `status` | string (default `pending`) | **Lifecycle** | Overcomplicated: 6 states → simplify to 3 (draft, published, archived) |
| `commission_type` | string (default `percentage`) | Commission | — |
| `commission_rate` | unsignedInteger (default 1000) | Commission | — |
| `currency` | string(3) (nullable) | Money | — |
| `cookie_days` | unsignedSmallInteger (nullable) | Tracking | — |
| `is_featured` | boolean (default false) | Curation flag | OK — curation flag, keep boolean |
| `is_public` | boolean (default true) | **Visibility** | **Replace with `visibility` string enum** |
| `requires_approval` | boolean (default true) | Application gate | OK — structural |
| `landing_url` | string (nullable) | Destination | — |
| `restrictions` | json (nullable) | Rules | — |
| `metadata` | json (nullable) | Extensibility | — |
| `starts_at` | timestampTz (nullable) | Scheduling | OK — keep |
| `ends_at` | timestampTz (nullable) | Scheduling | OK — keep |
| `created_at` | timestampTz | — | — |
| `updated_at` | timestampTz | — | — |

**Lifecycle states:** `draft` → `published` → `archived` (3-state linear, BackedEnum)

**Missing lifecycle timestamps:**
- `published_at timestampTz nullable` — when offer was published
- `archived_at timestampTz nullable` — when offer was archived

**Missing column:**
- `visibility` string (default `public`) — replaces `is_public` boolean

**Model:** `AffiliateOffer` — uses `ScopesBySiteOwner`, `HasCommerceAudit`,
`LogsCommerceActivity`.

---

### 2.4 `affiliate_network_offer_creatives`

| Column | Type | Current Role | Problem |
|---|---|---|---|
| `id` | uuid | PK | — |
| `offer_id` | foreignUuid | Parent (offer) | — |
| `type` | string (default `banner`) | Creative type | — |
| `name` | string | Identity | — |
| `description` | text (nullable) | Metadata | — |
| `url` | string (nullable) | Link | — |
| `file_path` | string (nullable) | Asset | — |
| `width` | unsignedSmallInteger (nullable) | Dimensions | — |
| `height` | unsignedSmallInteger (nullable) | Dimensions | — |
| `alt_text` | string (nullable) | Accessibility | — |
| `html_code` | text (nullable) | Embed | — |
| `is_active` | boolean (default true) | Config toggle | OK — admin config toggle, keep boolean |
| `sort_order` | unsignedInteger (default 0) | Ordering | — |
| `metadata` | json (nullable) | Extensibility | — |
| `created_at` | timestampTz | — | — |
| `updated_at` | timestampTz | — | — |

**Creatives are admin configuration entities associated with offers.** `is_active` boolean is
appropriate. No status column or timestamps needed.

**Model:** `AffiliateOfferCreative` — uses `HasCommerceAudit`, `LogsCommerceActivity`.

---

### 2.5 `affiliate_network_offer_applications`

| Column | Type | Current Role | Problem |
|---|---|---|---|
| `id` | uuid | PK | — |
| `offer_id` | foreignUuid | Parent (offer) | — |
| `affiliate_id` | foreignUuid | Parent (affiliate) | — |
| `status` | string (default `pending`) | **Lifecycle** | No `approved_at`, `rejected_at`, `revoked_at` |
| `reason` | text (nullable) | Application reason | — |
| `rejection_reason` | text (nullable) | Rejection detail | — |
| `reviewed_by` | string (nullable) | Reviewer identity | — |
| `reviewed_at` | timestampTz (nullable) | Review timestamp | OK — keep |
| `metadata` | json (nullable) | Extensibility | — |
| `created_at` | timestampTz | — | — |
| `updated_at` | timestampTz | — | — |

**Lifecycle states:** `pending` → `approved` | `rejected` | `revoked`

**Missing lifecycle timestamps:**
- `approved_at timestampTz nullable` — when application was approved
- `rejected_at timestampTz nullable` — when application was rejected
- `revoked_at timestampTz nullable` — when application was revoked

**Model:** `AffiliateOfferApplication` — uses `ScopesByAffiliateOwner`, `HasCommerceAudit`,
`LogsCommerceActivity`.

---

### 2.6 `affiliate_network_offer_links`

| Column | Type | Current Role | Problem |
|---|---|---|---|
| `id` | uuid | PK | — |
| `offer_id` | foreignUuid | Parent (offer) | — |
| `affiliate_id` | foreignUuid | Parent (affiliate) | — |
| `site_id` | foreignUuid (nullable) | Optional site context | — |
| `code` | string(32) (unique) | Tracking code | — |
| `target_url` | string | Destination | — |
| `custom_parameters` | string (nullable) | Params | — |
| `sub_id` / `sub_id_2` / `sub_id_3` | string (nullable) | Tracking sub-ids | — |
| `clicks` | unsignedBigInteger (default 0) | Metric | OK |
| `conversions` | unsignedBigInteger (default 0) | Metric | OK |
| `revenue` | unsignedBigInteger (default 0) | Metric | OK |
| `is_active` | boolean (default true) | Config toggle | OK — config toggle, keep boolean |
| `expires_at` | timestampTz (nullable) | Expiry | OK — keep |
| `metadata` | json (nullable) | Extensibility | — |
| `created_at` | timestampTz | — | — |
| `updated_at` | timestampTz | — | — |

**Links are config entities.** `is_active` boolean is appropriate. Keep `expires_at`.

**Model:** `AffiliateOfferLink` — uses `ScopesByAffiliateOwner`. **Missing `HasCommerceAudit`,
`LogsCommerceActivity`, and `Auditable` contract.**

---

## 3. Problems Summary

### P1 — Offer lifecycle overcomplicated (6 states → 3)

Current 6 states (draft, pending, active, paused, expired, rejected) can be simplified to 3
linear states (draft → published → archived) with `starts_at`/`ends_at` handling scheduling.
`BackedEnum` is the right tool for 3 linear states.

### P2 — `is_public` boolean on offers instead of `visibility` enum

Replace with `visibility` string column (values: `public`, `private`, `unlisted`). Offers are
content entities where visibility semantics matter.

### P3 — Status columns without transition timestamps

| Table | Status States | Missing Columns |
|---|---|---|
| `offers` | draft → published → archived | `published_at`, `archived_at` |
| `offer_applications` | pending → approved | rejected | revoked | `approved_at`, `rejected_at`, `revoked_at` |

### P4 — Missing audit on `AffiliateOfferLink`

`AffiliateOfferLink` does not implement `Auditable` and lacks `HasCommerceAudit` /
`LogsCommerceActivity`. All other models in the package have these traits.

---

## 4. Recommended Structure

### 4.1 New columns (migrations to add)

#### `affiliate_network_sites`

No changes. Existing `status` string + `verified_at` is sufficient for config-like entity.

#### `affiliate_network_offer_categories`

No changes. `is_active` boolean is appropriate for admin config toggle.

#### `affiliate_network_offers`

```
visibility     string default 'public'     — replaces is_public
published_at   timestampTz nullable       — when status set to published
archived_at    timestampTz nullable       — when status set to archived
```
Drop: `is_public`
Keep: `is_featured` (curation flag, boolean)
Simplify `status` values: `draft`, `published`, `archived`

#### `affiliate_network_offer_creatives`

No changes. `is_active` boolean is appropriate.

#### `affiliate_network_offer_applications`

```
approved_at    timestampTz nullable
rejected_at    timestampTz nullable
revoked_at     timestampTz nullable
```
Keep: `reviewed_at` (exists)

#### `affiliate_network_offer_links`

No column changes. Keep `is_active` boolean. Add audit traits to model.

### 4.2 New enums (`src/Enums/`)

```
src/Enums/OfferStatus.php          — Draft, Published, Archived
src/Enums/OfferVisibility.php      — Public, Private, Unlisted
src/Enums/ApplicationStatus.php    — Pending, Approved, Rejected, Revoked
```

Keep status values as BackedEnum strings. No `spatie/laravel-model-states` for these simple
lifecycles (2-4 linear states).

### 4.3 Model changes

- `AffiliateOffer`: replace `is_public` with `visibility` cast; simplify status to 3 values;
  add `published_at` / `archived_at` casts; keep `is_featured` boolean cast
- `AffiliateOfferApplication`: cast `status` to `ApplicationStatus`; add `approved_at` /
  `rejected_at` / `revoked_at` casts
- `AffiliateOfferLink`: add `implements Auditable`, use `HasCommerceAudit`,
  `LogsCommerceActivity`
- `AffiliateSite`, `AffiliateOfferCategory`, `AffiliateOfferCreative`: no column changes

---

## 5. Refactoring Plan — Parallel-Agent Checklist

### Agent A: Enums

- [x] Create `src/Enums/OfferStatus.php`
- [x] Create `src/Enums/OfferVisibility.php`
- [x] Create `src/Enums/ApplicationStatus.php`

### Agent B: Migration — offers

- [x] Add `visibility` column (default `'public'`), add `published_at` and `archived_at`
  columns
- [x] Backfill: where `is_public = true` → `visibility = 'public'`, where `false` →
  `visibility = 'private'`
- [x] Backfill: where `status = 'active'` → `status = 'published'` and `published_at = updated_at`;
  where `status IN ('expired', 'rejected')` → `status = 'archived'` and `archived_at = updated_at`
- [x] Drop `is_public`

### Agent C: Migration — applications

- [x] Add `approved_at`, `rejected_at`, `revoked_at` columns

### Agent D: Model updates

- [x] Update `AffiliateOffer` — drop `is_public` from casts/fillable, add `visibility` cast,
  `published_at` / `archived_at` casts; simplify status handling
- [x] Update `AffiliateOfferApplication` — add `approved_at` / `rejected_at` / `revoked_at`
  to casts and fillable; cast `status` to `ApplicationStatus`
- [x] Update `AffiliateOfferLink` — add `implements Auditable`, `HasCommerceAudit`,
  `LogsCommerceActivity`

### Agent E: Tests

- [x] Test offer lifecycle: draft → published → archived with timestamp assertions
- [x] Test application lifecycle: pending → approved/rejected/revoked with timestamp assertions
- [x] Test `visibility` replaces `is_public` in all entry points
- [x] Test `is_featured` remains boolean (not changed)
- [x] Test audit trail for `AffiliateOfferLink`
- [x] Test config entities (categories, creatives, links) retain `is_active` boolean

### Agent F: Filament alignment

- [x] Update offer resource: replace `is_public` toggle with `visibility` select
- [x] Update application resource: use new `*_at` timestamps
- [x] No changes needed for categories, creatives, links (keep `is_active` toggles)

---

## 6. Migration Strategy

### Phase 1: Add-only

1. Add `visibility` column to offers (default `'public'`)
2. Add `published_at`, `archived_at` to offers
3. Add `approved_at`, `rejected_at`, `revoked_at` to applications

### Phase 2: Backfill

4. Backfill `visibility` from `is_public`
5. Backfill `published_at` / `archived_at` from existing status values
6. Simplify offer status values via backfill query

### Phase 3: Drop old columns

7. Drop `is_public` from offers

No backward compatibility. Filament resources and consumers must be updated in the same deployment.

### Migration file ordering:

```
2000_01_01_000007_add_offer_visibility_and_lifecycle_timestamps.php
2000_01_01_000008_add_application_lifecycle_timestamps.php
2000_01_01_000009_drop_offer_is_public_column.php
```

---

## 7. Verification Commands

```bash
# 1. Check is_public removed from offers migration
rg -n "is_public" packages/affiliate-network/database/migrations/

# 2. Check is_active still present (should remain for categories/creatives/links)
rg -n "is_active" packages/affiliate-network/database/migrations/

# 3. Check is_featured still present on offers (should remain)
rg -n "is_featured" packages/affiliate-network/database/migrations/

# 4. Check new *_at columns exist in migrations
rg -n "published_at|archived_at|approved_at|rejected_at|revoked_at" packages/affiliate-network/database/migrations/

# 5. Verify enums exist
ls packages/affiliate-network/src/Enums/

# 6. Verify no state classes were created
ls packages/affiliate-network/src/States/ 2>/dev/null && echo "ERROR: states should not exist" || echo "OK"

# 7. Verify offer model has visibility cast, not is_public
rg -n "visibility|is_public" packages/affiliate-network/src/Models/AffiliateOffer.php

# 8. Verify AffiliateOfferLink has auditable traits
rg -n "HasCommerceAudit|LogsCommerceActivity|implements Auditable" packages/affiliate-network/src/Models/AffiliateOfferLink.php

# 9. PHPStan
./vendor/bin/phpstan analyse packages/affiliate-network/src --level=6

# 10. Run tests
./vendor/bin/pest --parallel packages/affiliate-network/tests/
```
