---
title: Affiliate Network Lifecycle Audit
package: affiliate-network
related: affiliates, filament-affiliate-network
status: audit
---

# Affiliate Network Lifecycle Audit

## Scope

This audit covers two related packages:

- `packages/affiliate-network/` (Composer: `aiarmada/affiliate-network`) — the multi-merchant affiliate marketplace (Sites, Offers, OfferCategories, OfferCreatives, OfferApplications, OfferLinks).
- `packages/affiliates/` (Composer: `aiarmada/affiliates`) — the core affiliate engine (Affiliate, Program, Membership, Conversion, Payout, Link, PayoutMethod, PayoutHold, FraudSignal, Training, TaxDocument, Rank, etc.).

Both packages together implement the "affiliates-network" surface referenced in the request. The audit is codebase-aware: it favours the smallest, code-driven changes that fit the existing pattern (Spatie model-states for `Affiliate`, `Payout`, `Conversion`; plain `string` `status` columns elsewhere) and recommends architecture changes only where a copy-the-pattern fix would spread a known problem.

Where both packages have the same shape (e.g. `is_active` boolean on a "link" record), the recommendation is to refactor at the source, not invent per-package workarounds.

---

## Cross-cutting findings (both packages)

### F1. `is_active` is a vague boolean that means different things in each table

| Table | Column | Current meaning (inferred) | Real intent |
| --- | --- | --- | --- |
| `affiliate_links` | `is_active` | "Admin disabled this link" | Link is currently in use (not disabled, not expired) |
| `affiliate_commission_rules` | `is_active` | "Rule is enabled" + `starts_at`/`ends_at` window | Rule is enabled |
| `affiliate_commission_templates` | `is_active` | "Template is selectable as default" | Template is selectable |
| `affiliate_training_modules` | `is_active` | "Module is visible" | Module is published/visible |
| `affiliate_network_offer_categories` | `is_active` | "Category is browsable" | Category is visible |
| `affiliate_network_offer_creatives` | `is_active` | "Creative is shown on offer page" | Creative is in rotation |
| `affiliate_network_offer_links` | `is_active` | "Link still resolves on public redirect" | Link resolves (not disabled, not expired) |
| `affiliate_payout_methods` | `is_default` | "This is the default method" | Selection marker — not a lifecycle field |
| `affiliate_payout_methods` | `is_verified` | "Method has been verified" | Verification — already paired with `verified_at` (correct pattern) |

`is_active` here is a *catch-all* and only sometimes means "not disabled". The same column is read by `scopeActive()` on `AffiliateCommissionRule` as a pure enable flag, by `OfferLinkService::resolveLink()` as a public-redirect gate, and by the `AffiliateOfferCategory` admin filter as a visibility flag. None of these are equivalent.

**Recommendation (architecture-first at the package boundary):**

1. Treat `is_active` as a single business concept in this package family: "admin disabled this record" → convert to a nullable `disabled_at timestampTz` (rule 4 in the audit spec). The model exposes `isActive(): bool { return $this->disabled_at === null; }`.
2. Where the column is purely a "this row is selectable / in rotation" flag (e.g. `commission_templates.is_active`, `commission_templates.is_default`, `offer_creatives.is_active`), it is *not* a lifecycle field — it is a configuration bit. Document it as such, keep as `boolean`, and **do not** add a `disabled_at` column.
3. For `affiliate_payout_methods.is_verified`: already correct (paired with `verified_at`). Leave alone.

Because the same column is reused for semantically different flags, the safest in-place change is to leave the columns where they are unambiguous and add a one-line PHPDoc note on each `$casts` block stating the meaning. A full rename to `disabled_at` should be deferred to a dedicated refactor issue, not slipped into a behaviour change.

### F2. `Affiliate` already uses `activated_at` correctly — preserve it

`affiliates.activated_at` is set by `CreateAffiliate` (when `RegistrationApprovalMode::Auto` produces `Active::class`) and by `ApproveAffiliate`. It is not just a boolean alias: it is the moment the affiliate first went live. **Keep as-is.** It is the only place in the package family where the "action timestamp" pattern is already used correctly.

### F3. Status is stringly-typed on the marketplace models — inconsistent with the rest of the package

`Affiliate` uses Spatie model-states (proper lifecycle FSM). `Payout` and `Conversion` use Spatie model-states. `ProgramMembership` uses a real enum (`MembershipStatus`). `FraudSignal` uses a real enum (`FraudSignalStatus`). `Program` uses a real enum (`ProgramStatus`).

The four marketplace models in `affiliate-network` (`Site`, `Offer`, `OfferCategory`, `OfferCreative`, `OfferApplication`, `OfferLink`) and three in `affiliates` (`SupportTicket`, `TaxDocument`) use raw `string` columns with class constants for the values. This works but loses:

- Type safety at the application boundary (Filament `Select` options are duplicated as raw arrays in the resources — see `AffiliateOfferResource::form()`).
- A single source of truth for valid transitions.
- Coloured badges, labels, descriptions in one place.

**Recommendation:** convert the marketplace string statuses to proper enums in a follow-up. This is a *consistency* refactor, not a behaviour change. The current `STATUS_*` constants become enum cases, the column type stays `string`. State transition logic on `Offer` (`activate`/`pause` actions) can later move into a Spatie state class if/when transitions grow.

This is a larger refactor than fits in a single audit cycle, so flag it as `wontfix-for-now` and only convert `AffiliateSite::status` and `AffiliateOffer::status` first (the two that have visible lifecycle meaning). Defer the rest.

### F4. Approval is split across `status` and timestamps inconsistently

- `affiliate_program_memberships`: `status` (MembershipStatus enum: Pending/Approved/Rejected/Suspended) + `approved_at` + `approved_by` + `expires_at`. **Correct pattern.**
- `affiliate_network_offer_applications`: `status` (string) + `reviewed_at` + `reviewed_by` + `reason` + `rejection_reason`. **Mostly correct** — `rejection_reason` and `reason` overlap and are not strongly separated (see F7).
- `affiliate_conversions`: `status` (Spatie state: Pending/Qualified/Approved/Rejected/Paid) + `approved_at`. **No `approved_by`** — no audit trail of *who* approved a conversion. In an MLM/affiliate system with auto-approval, this matters.
- `affiliate_fraud_signals`: `status` (FraudSignalStatus enum) + `reviewed_at` + `reviewed_by`. **Correct pattern.**

**Recommendation:** add `approved_by` (or `rejected_by`/`reviewed_by` depending on the entity) to `affiliate_conversions` and `affiliate_payouts`. See per-model section for migrations.

### F5. `is_public` and `requires_approval` are configuration, not lifecycle

These are programme/offer-level configuration flags that *change the join flow* but do not represent a stage in a lifecycle. They are correctly boolean. Leave them as-is, but document the meaning on the model PHPDoc (already done on `AffiliateOffer`, not done on `AffiliateProgram`).

### F6. `expires_at` is overloaded as both "lifecycle end" and "TTL"

Used correctly on:
- `affiliate_attributions.expires_at` (cookie TTL) — fine.
- `affiliate_payout_holds.expires_at` (auto-release hold) — fine.
- `affiliate_program_memberships.expires_at` (membership duration) — fine.
- `affiliate_network_offer_links.expires_at` (link TTL) — fine.
- `affiliate_network_offers.ends_at` (offer campaign window) — fine.

It is *not* a cancellation, postponement, or archival timestamp. No recommendation needed — it is used correctly throughout.

### F7. `reason` and `rejection_reason` overlap on `AffiliateOfferApplication`

`reason` is the *applicant's* reason for applying; `rejection_reason` is the *reviewer's* reason for rejection. They are conceptually distinct (one is the application pitch, the other is a rejection note). Currently they share a column slot with no formal separation in the model. No data integrity issue, just clarity.

**Recommendation:** keep both columns but rename the *fillable* PHPDoc to make the role distinction explicit, and rename `reason` → `application_reason` (or document why both stay as-is). Defer — cosmetic.

### F8. No `published_at`, `cancelled_at`, `archived_at`, `delayed_at`, `postponed_at` exist anywhere

The package family is **not** an "event-like" domain. None of the entities need those timestamps. **No recommendation.** This is a non-issue — calling it out explicitly so the audit is complete.

---

## Per-model audit

### M1. `AffiliateSite` (affiliate-network)

```php
status             string default 'pending'        // pending|verified|suspended|rejected
verified_at        timestampTz nullable
verification_method string nullable
verification_token string nullable
```

- **Status is the lifecycle.** `isVerified()` is a derived check on `status === verified && verified_at !== null`. **Good** — but `verified_at` is mutated *only* by `SiteVerificationService::verify()` and the Filament `verify` action. The `verified_at` column is therefore the *event timestamp*; the `isVerified()` accessor correctly uses both.
- **Risk:** `isVerified()` requires `status = verified && verified_at !== null`, but the migration does not enforce the invariant. A future write path could set `status = 'verified'` without setting `verified_at` (or vice-versa). Mitigate by funnelling all state changes through `SiteVerificationService::verify()` and a single `markVerified`/`markSuspended` method.
- **No `approved_at`/`approved_by`.** Verification is the only approval event, and `verification_method` is the closest analogue. Adequate.
- **No `cancelled_at`.** `STATUS_SUSPENDED` is a soft hold, `STATUS_REJECTED` is the terminal state. No archival timestamp — sites are deleted in the cascade, not archived. **Acceptable** for this domain.

**Recommended changes:**

1. Wrap the four transitions in domain methods (`markVerified`, `markSuspended`, `markRejected`) and add the corresponding events.
2. Add a `verified_by` nullable foreign key for audit parity with `offer_applications.reviewed_by`. *Optional.*
3. Convert `status` from `string` to a `SiteStatus` enum (rule 1 / F3). Defer to a follow-up.

**Migration changes:** add `verified_by` if you decide to audit.
**Risk level:** low.
**Backward compatibility:** none (string column, additive enum later).

### M2. `AffiliateOffer` (affiliate-network)

```php
status              string default 'pending'      // draft|pending|active|paused|expired|rejected
is_featured         bool default false
is_public           bool default true
requires_approval   bool default true
starts_at           timestampTz nullable
ends_at             timestampTz nullable
```

- **Status is the lifecycle.** `STATUS_DRAFT → PENDING → ACTIVE ↔ PAUSED → EXPIRED|REJECTED`. The Filament `activate`/`pause` actions are the only transition paths.
- **`isActive()`** is computed: `status === active && now() in [starts_at, ends_at]`. **Correct** (rule 16 / 12). No `disabled_at` is needed.
- **No `approved_at`/`approved_by`** for the transition into `active`. Currently admin-driven via Filament. If admin approval is ever delegated to a non-`users` actor or to a scheduled job, add `approved_at` + `approved_by`. *Optional now, mandatory later.*
- **`STATUS_EXPIRED`** is a self-managed transition (driven by `ends_at` + a scheduler). No expiry timestamp — `ends_at` is the *schedule*, not "when it was marked expired". If the business ever asks "when was this offer actually expired", add `expired_at` (nullable) and let the scheduler set both. *Defer.*
- **No `cancelled_at`**, **`rejected_at`**, **`rejected_by`**. `STATUS_REJECTED` is the rejection state but the *who* and *when* are lost. **Add `rejected_at timestampTz` + `rejected_by` (nullable)** to match the `offer_applications` pattern.
- **No `archived_at`.** Deletion cascades to creatives/applications/links. Acceptable.

**Recommended changes:**

1. Add `rejected_at timestampTz nullable` + `rejected_by string nullable` to `affiliate_network_offers`.
2. Wrap `STATUS_ACTIVE` and `STATUS_PAUSED` transitions in an `OfferLifecycle` service (or just keep the inline Filament actions, they are the only callers today).
3. Convert `status` to `OfferStatus` enum. Defer.

**Migration changes:**
```php
$table->timestampTz('rejected_at')->nullable();
$table->string('rejected_by')->nullable();
```
**Risk level:** low.
**Data migration:** none (new nullable columns).
**Backward compatibility:** none.

### M3. `AffiliateOfferCategory` (affiliate-network)

```php
is_active bool default true
sort_order int
```

- **No `status`.** `is_active` here is "is this category browsable" — a visibility/configuration flag, not a lifecycle. No `disabled_at` is needed because deletion is the only terminal action and children are re-parented in the `booted()` hook.
- **No `published_at`, no `archived_at`.** Categories do not have a publish event in this domain.

**Recommended changes:**

1. Document the meaning of `is_active` on the model PHPDoc and the migration comment.
2. Add `archived_at` only if/when "hide category without deleting" becomes a real use case. Do not add speculatively.

**Migration changes:** none.
**Risk level:** none.

### M4. `AffiliateOfferCreative` (affiliate-network)

```php
is_active bool default true
sort_order int
```

- Same as M3. `is_active` = "is this creative in the active rotation". Not a lifecycle. No change.

**Recommended changes:** document.
**Migration changes:** none.

### M5. `AffiliateOfferApplication` (affiliate-network)

```php
status              string default 'pending'      // pending|approved|rejected|revoked
reason              text nullable                   // applicant's reason (pitch)
rejection_reason    text nullable                   // reviewer's reason
reviewed_by         string nullable
reviewed_at         timestampTz nullable
```

- **Status is the lifecycle.** The four states cover apply/approve/reject/revoke. Reuse and revoke are intentionally distinct (rejection is a never-approve decision, revoke is an after-the-fact cancellation). **Good.**
- **No `approved_at` separate from `reviewed_at`.** `reviewed_at` is set on approve, reject, and revoke alike. The model cannot answer "when was this approved" without also reading `status`. **Acceptable** for this domain because the audit log is not the source of truth — but consider splitting:
  - `reviewed_at` keeps its current meaning.
  - For an *approval*, the transition is also the *approval timestamp*. If the business later wants "time to approval" metrics, store `approved_at` independently.
- **No `cancelled_at`.** `STATUS_REVOKED` is the terminal cancellation. Adequate.
- **`reason` and `rejection_reason` overlap semantically** for some callers (F7). No data corruption, but the column naming could be tightened.

**Recommended changes:**

1. If you adopt `Affiliates` model-states, this is a strong candidate to convert. State transitions are: `Pending → Approved`, `Pending → Rejected`, `Approved → Revoked`.
2. Split `reviewed_at` into `approved_at` / `rejected_at` / `revoked_at` if you want per-event timestamps. *Optional.*
3. Rename `reason` → `application_reason` for clarity. *Cosmetic, defer.*

**Migration changes:** none required.
**Risk level:** low.

### M6. `AffiliateOfferLink` (affiliate-network)

```php
is_active   bool default true
expires_at  timestampTz nullable
```

- `is_active` + `expires_at` together: `is_active` is the admin-disable switch, `expires_at` is the auto-expire. Combined: `isActive() === is_active && ! isExpired()`.
- The `OfferLinkService::resolveLink()` and the public redirect use `where('is_active', true)`. After this, an `expires_at` in the past is not filtered at the DB level — only `isExpired()` exposes that fact. **Bug risk:** the redirect controller must also check `isExpired()`.

**Recommended changes:**

1. Add `disabled_at timestampTz nullable` and migrate `is_active` to be derived from it (F1). Then `OfferLinkService::resolveLink()` and the redirect controller query `whereNull('disabled_at') AND (expires_at IS NULL OR expires_at > now())`.
2. Alternatively, keep `is_active` and add a `scopePubliclyResolvable` that combines both filters. Smaller diff.

**Migration changes (option B, smallest):**
```php
// no migration; add a scope on the model
public function scopePubliclyResolvable(Builder $query): Builder
{
    return $query->where('is_active', true)
        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
}
```
Replace the `where('is_active', true)` in `OfferLinkService::resolveLink()` and the redirect controller with `$query->publiclyResolvable()`. Risk: still leaves a window where `is_active = false` is set without `disabled_at` — but that is acceptable as a configuration bit, not a lifecycle event.
**Risk level:** low.

### M7. `Affiliate` (affiliates)

Already audited across many cycles. The relevant state surface:

```php
status              AffiliateStatus (Spatie state)  // draft|pending|active|paused|disabled
activated_at        timestampTz nullable
```

- `ApproveAffiliate` sets both `status = Active` *and* `activated_at = now()`. This is the correct combined pattern (rule 3 / 12).
- `RejectAffiliate` sets `status = Disabled` but does **not** set any rejection timestamp. There is no `rejected_at` / `rejected_by`.
- `booted()` fires `AffiliateActivated` only when `status` changes to `Active` — so the `activated_at` field captures the *most recent* activation. If a record goes `Active → Disabled → Active`, the *first* activation time is lost. **Acceptable** for an event-fired design, but a `rejected_at` should be added for the rejection path so we can show "rejected on X by Y".

**Recommended changes:**

1. Add `rejected_at timestampTz nullable` + `rejected_by string nullable` + `rejection_reason text nullable` to `affiliates`.
2. Update `RejectAffiliate` to set all three.
3. Consider whether `paused_at` is needed (rule 6 / 7 — the user *pauses* vs. is *paused by admin* distinction). Probably not for the same reason `activated_at` is the most-recent: pause history is encoded in the event log. Leave as is.

**Migration changes:**
```php
$table->timestampTz('rejected_at')->nullable();
$table->string('rejected_by')->nullable();
$table->text('rejection_reason')->nullable();
```
**Risk level:** low.
**Backward compatibility:** none (additive).
**Data migration:** none.

### M8. `AffiliateProgram` (affiliates)

```php
status              ProgramStatus (string enum)  // draft|active|paused|archived
starts_at           timestampTz nullable
ends_at             timestampTz nullable
requires_approval   bool default true
is_public           bool default true
```

- `isActive()` correctly combines `status = active && now() in [starts_at, ends_at]`. **Correct** (rule 16).
- `ProgramStatus::Archived` exists but nothing writes to it. There is no `archived_at`. **Add `archived_at timestampTz nullable`** + `archived_by` for audit parity (rule 11).
- `isOpen()` = `isActive() && is_public`. **Good** (rule 5: `is_public` is acceptable as a binary visibility flag for a small public/private system).
- **No `published_at`**. The program moves from `draft` to `active` (effectively "published"). Whether `published_at` is needed depends on whether the business wants "we first published this on X". Currently not asked. **Defer.**

**Recommended changes:**

1. Add `archived_at timestampTz nullable` + `archived_by string nullable`.
2. Document `requires_approval` is configuration, not lifecycle.

**Migration changes:**
```php
$table->timestampTz('archived_at')->nullable();
$table->string('archived_by')->nullable();
```
**Risk level:** low.

### M9. `AffiliateProgramMembership` (affiliates)

```php
status         MembershipStatus (string enum)  // pending|approved|rejected|suspended
applied_at     timestampTz
approved_at    timestampTz nullable
approved_by    string nullable
expires_at     timestampTz nullable
```

- `MembershipStatus` enum is correct (rule 1).
- `approve(?string $approvedBy)` sets status, `approved_at`, and `approved_by` together. **Good** (rule 6).
- `reject()` sets only `status` — no `rejected_at`, no `rejection_reason`, no `rejected_by`. **Inconsistent** with the application pattern in M5 and rule 10.
- `suspend()` sets only `status` — no `suspended_at` / `suspended_by`. Same issue.

**Recommended changes:**

1. Add `rejected_at`, `rejected_by`, `rejection_reason` (nullable) and have `reject()` set them.
2. Add `suspended_at`, `suspended_by` (nullable) and have `suspend()` set them. *Optional but recommended for auditability.*

**Migration changes:**
```php
$table->timestampTz('rejected_at')->nullable();
$table->string('rejected_by')->nullable();
$table->text('rejection_reason')->nullable();
$table->timestampTz('suspended_at')->nullable();
$table->string('suspended_by')->nullable();
```
**Risk level:** low.

### M10. `AffiliateConversion` (affiliates)

```php
status       ConversionStatus (Spatie state)  // pending|qualified|approved|rejected|paid
occurred_at  timestampTz
approved_at  timestampTz nullable
```

- `approved_at` is set in `booted()`'s `created` and `updated` handlers when status transitions to `Approved`. **Good** — but no `approved_by` (F4).
- No `rejected_at` / `rejected_by` / `rejection_reason` when conversion is rejected. **Inconsistent.**
- No `paid_at` separate from `status = Paid`. Adequate because the payout's `paid_at` records the actual money movement.

**Recommended changes:**

1. Add `approved_by` + `rejected_at` + `rejected_by` + `rejection_reason` (all nullable).
2. Update `booted()` to set them when status transitions.

**Migration changes:**
```php
$table->string('approved_by')->nullable();
$table->timestampTz('rejected_at')->nullable();
$table->string('rejected_by')->nullable();
$table->text('rejection_reason')->nullable();
```
**Risk level:** low.

### M11. `AffiliatePayout` (affiliates)

```php
status        PayoutStatus (Spatie state)  // pending|processing|completed|failed|cancelled
scheduled_at  timestampTz nullable
paid_at       timestampTz nullable
```

- `paid_at` is the payout completion timestamp. **Good.**
- No `cancelled_at` / `cancelled_by` / `cancellation_reason` (rule 10). `STATUS_CANCELLED` exists but writes only set `status`.
- No `failed_at` / `failure_reason` (the `FailedPayout` state has no metadata).

**Recommended changes:**

1. Add `cancelled_at` + `cancelled_by` + `cancellation_reason`.
2. Add `failed_at` + `failure_reason`.
3. Add `processed_at` (between `scheduled_at` and `paid_at`) if the business ever wants "time spent in processing". *Optional.*

**Migration changes:**
```php
$table->timestampTz('cancelled_at')->nullable();
$table->string('cancelled_by')->nullable();
$table->text('cancellation_reason')->nullable();
$table->timestampTz('failed_at')->nullable();
$table->text('failure_reason')->nullable();
```
**Risk level:** low.

### M12. `AffiliateFraudSignal` (affiliates)

```php
status        FraudSignalStatus (enum)  // detected|reviewed|dismissed|confirmed
detected_at   timestampTz
reviewed_at   timestampTz nullable
reviewed_by   string nullable
```

- The model has *three* review actions (`markAsReviewed`, `dismiss`, `confirm`) and they all set `reviewed_at` + `reviewed_by`. `status` is the lifecycle, `reviewed_at` is the *event* timestamp. **Good** — this is the most consistent state model in the package.
- `description` is the *rule's* description, not the *case* description. The case-level notes are missing. **Defer.**

**Recommended changes:** none required. Reference implementation for the rest of the package.

### M13. `AffiliatePayoutMethod` (affiliates)

```php
is_verified  bool default false
is_default   bool default false
verified_at  timestampTz nullable
```

- `is_verified` + `verified_at` is the correct pattern (rule 3/9). `verify()` sets both. **Good.**
- `is_default` is a selection marker, not a lifecycle. Keep as boolean.
- `details` is `encrypted:array` — correct for PCI-adjacent data.

**Recommended changes:** none.

### M14. `AffiliatePayoutHold` (affiliates)

```php
reason       string
notes        text nullable
expires_at   timestampTz nullable
placed_by    string nullable
released_at  timestampTz nullable
```

- `isActive()` is derived: `released_at === null && (expires_at === null || expires_at > now)`. **Correct** (rule 9 / 15).
- `placed_by` exists; no `placed_at` (uses `created_at`).
- No `released_by`. **Add `released_by`** for auditability.

**Recommended changes:**

1. Add `released_by string nullable`.
2. Update `release()` to accept an optional `$releasedBy` actor.

**Migration changes:**
```php
$table->string('released_by')->nullable();
```
**Risk level:** low.

### M15. `AffiliateTaxDocument` (affiliates)

```php
status         string default 'pending'  // raw string, not enum
generated_at   timestampTz nullable
sent_at        timestampTz nullable
```

- `status` is a raw string — the only "lifecycle status" in the package that is *not* an enum or Spatie state. **Convert to `TaxDocumentStatus` enum** (F3) if you want to enforce values.
- `generated_at` and `sent_at` are the two event timestamps. **Good pattern.**
- No `approved_at` / `approved_by` for tax-relevant approval. If the document must be reviewed, add it. *Optional.*

**Recommended changes:** add a `TaxDocumentStatus` enum in a follow-up.

### M16. `AffiliateSupportTicket` (affiliates)

```php
subject   string
category  string default 'general'
priority  string default 'normal'
status    string default 'open'   // raw string
```

- `status` is a raw string. **Convert to `SupportTicketStatus` enum** in a follow-up.
- No `closed_at` / `closed_by`. **Add `closed_at timestampTz nullable` + `closed_by string nullable`** so the lifecycle "open → in_progress → resolved → closed" can be audited.

**Migration changes (if/when converted):**
```php
$table->timestampTz('closed_at')->nullable();
$table->string('closed_by')->nullable();
```

### M17. `AffiliateTrainingModule` (affiliates)

```php
is_active  bool default true
is_required bool default false
```

- `is_active` is "module is published/visible", not lifecycle. No `published_at`, no `archived_at`. **No change** unless the business starts needing audit (probably not for training content).
- `is_required` is configuration.

**Recommended changes:** document.

### M18. `AffiliateCommissionRule` (affiliates)

```php
is_active  bool default true
starts_at  timestampTz nullable
ends_at    timestampTz nullable
```

- `is_active` + window is "is this rule in effect". `isActive()` and `scopeActive()` correctly combine. No `disabled_at` needed — this is a configuration bit, not a lifecycle event.
- No `archived_at`. Rules are deleted, not archived. **Acceptable.**

**Recommended changes:** none.

### M19. `AffiliateCommissionTemplate` (affiliates)

```php
is_default bool default false
is_active  bool default true
```

- Both are configuration bits. No lifecycle. No change.

### M20. `AffiliateCommissionPromotion` (affiliates)

```php
starts_at    timestampTz
ends_at      timestampTz
max_uses     int nullable
current_uses int default 0
```

- `isActive()` = "now in [starts_at, ends_at] AND current_uses < max_uses". **Correct.**
- No `status`, no `cancelled_at`. Promotions are scheduled, not cancelled. **Acceptable** for this domain.

### M21. `AffiliateRankHistory` (affiliates)

```php
from_rank_id, to_rank_id, reason (RankQualificationReason enum), qualified_at
```

- Pure history table. Already correct.

---

## Recommended structure (summary)

For each table, this is the *target* state after the recommended migrations above. The point of the table is to show what *not* to add speculatively.

| Table | `status` enum/state | Action timestamps | `_by` fields | Reason fields |
| --- | --- | --- | --- | --- |
| `affiliate_network_sites` | `SiteStatus` enum | `verified_at` | (optional `verified_by`) | — |
| `affiliate_network_offers` | `OfferStatus` enum | `rejected_at` | `rejected_by` | — |
| `affiliate_network_offer_applications` | `OfferApplicationStatus` enum (or Spatie state) | `reviewed_at` | `reviewed_by` | `application_reason`, `rejection_reason` |
| `affiliate_network_offer_links` | none — uses `is_active` + `expires_at` | `expires_at` | — | — |
| `affiliate_network_offer_categories` | none — `is_active` | — | — | — |
| `affiliate_network_offer_creatives` | none — `is_active` | — | — | — |
| `affiliates` | Spatie `AffiliateStatus` | `activated_at`, `rejected_at` | `rejected_by` | `rejection_reason` |
| `affiliate_programs` | `ProgramStatus` enum | `archived_at` | `archived_by` | — |
| `affiliate_program_memberships` | `MembershipStatus` enum | `approved_at`, `rejected_at`, `suspended_at`, `expires_at` | `approved_by`, `rejected_by`, `suspended_by` | `rejection_reason` |
| `affiliate_conversions` | Spatie `ConversionStatus` | `occurred_at`, `approved_at`, `rejected_at` | `approved_by`, `rejected_by` | `rejection_reason` |
| `affiliate_payouts` | Spatie `PayoutStatus` | `scheduled_at`, `paid_at`, `cancelled_at`, `failed_at` | `cancelled_by` | `cancellation_reason`, `failure_reason` |
| `affiliate_payout_methods` | none | `verified_at` | — | — |
| `affiliate_payout_holds` | none | `expires_at`, `released_at` | `released_by` | — |
| `affiliate_fraud_signals` | `FraudSignalStatus` enum | `detected_at`, `reviewed_at` | `reviewed_by` | — |
| `affiliate_tax_documents` | `TaxDocumentStatus` enum (new) | `generated_at`, `sent_at` | — | — |
| `affiliate_support_tickets` | `SupportTicketStatus` enum (new) | `closed_at` | `closed_by` | — |
| `affiliate_training_modules` | none — `is_active` | — | — | — |
| `affiliate_commission_rules` | none — `is_active` + window | `starts_at`, `ends_at` | — | — |
| `affiliate_commission_templates` | none — `is_active` | — | — | — |
| `affiliate_commission_promotions` | none — window + uses | `starts_at`, `ends_at` | — | — |
| `affiliate_links` | none — `is_active` | — | — | — |
| `affiliate_ranks` | n/a | — | — | — |
| `affiliate_rank_histories` | n/a | `qualified_at` | — | — |

**Explicitly NOT recommended** for this package family (no business need observed in any read):
- `published_at` (no record has a publish event)
- `disabled_at` (the `is_active` boolean is already a configuration bit, not a lifecycle event)
- `delayed_at` / `postponed_at` (no event-like records; offers and programs have schedule windows, not delays/postponements)
- `cancelled_at` on offers / creatives / categories (terminal states use `status` or are deleted)
- `visibility` (the public/private split is binary and `is_public` suffices)
- `approval_status` separate from `status` (every approval is a single transition, not a workflow)

---

## Migration strategy

For each additive migration:

1. Add nullable columns (no defaults). No data backfill needed.
2. Update models (fillable, casts, optional helper methods).
3. Update any *write* paths (Filament actions, services) to set the new fields.
4. Update tests.
5. Ship.

There is no need to drop the legacy `is_active` / `is_verified` / `is_default` booleans — they are still semantically correct as configuration bits. **Only** remove them if you have a separate, scoped refactor issue. Per the development guidelines, no mass reverts.

---

## Test changes (per package)

For `affiliates`:
- RejectAffiliate: assert `rejected_at`, `rejected_by`, `rejection_reason` are set.
- Affiliate: assert `activated_at` is set on approve and unchanged on subsequent activations.
- AffiliateProgramMembership: assert `rejected_at` / `suspended_at` on reject / suspend.
- AffiliateConversion: assert `approved_by` / `rejected_at` on transitions.
- AffiliatePayout: assert `cancelled_at` / `cancelled_by` on cancel.
- AffiliatePayoutHold: assert `released_by` on release.

For `affiliate-network`:
- AffiliateSite: assert `verified_at` is set by `SiteVerificationService::verify()` and not changed by the Filament `verify` action (or assert both paths set it).
- AffiliateOffer: assert `rejected_at` is set on reject path.
- AffiliateOfferLink: assert `resolveLink()` ignores expired links even when `is_active = true`.
- AffiliateOfferApplication: assert `reviewed_at` is set on approve / reject / revoke.

---

## Risk matrix

| Change | Risk | Reason |
| --- | --- | --- |
| Add `rejected_at` / `rejected_by` / `rejection_reason` to `affiliates` | Low | Additive nullable columns; only changes write paths that reject. |
| Add `archived_at` / `archived_by` to `affiliate_programs` | Low | Additive; no current writer. |
| Add `rejected_at` / `rejected_by` to `affiliate_network_offers` | Low | Additive. |
| Add `closed_at` / `closed_by` to `affiliate_support_tickets` | Low | Additive. |
| Add `released_by` to `affiliate_payout_holds` | Low | Additive. |
| Convert `is_active` to `disabled_at` on links/rules | Medium | Touches every read path that uses `is_active`; only do it if you also commit to migrating the 7+ call sites. |
| Convert `status` from string to enum on marketplace models | Low (data) / Medium (API) | Backwards compatible at the DB level; requires sweeping all read sites to use the enum. |
| Convert `status` to Spatie state machine on marketplace models | Medium | Adds transition validation; needs an explicit allowlist. |
| Add `approved_by` to `affiliate_conversions` | Low | Additive; needs `booted()` updates. |

---

## Final recommendation

1. **In this pass**, add the missing `_by` and action-timestamp fields listed in M1–M16 (adoption level: low risk, additive, audit-only).
2. **In a follow-up issue**, convert the marketplace `string` statuses (`Site`, `Offer`, `OfferCategory`, `OfferCreative`, `OfferApplication`, `TaxDocument`, `SupportTicket`) to enums. This is a consistency refactor; no behaviour change.
3. **In a separate, scoped refactor**, decide whether `is_active` on links/rules/creatives/categories should become `disabled_at`. Today it works; do not change it inside a behaviour PR.
4. **Do not** add `published_at`, `delayed_at`, `postponed_at`, `visibility`, or a separate `approval_status` — they do not correspond to any business event in this domain.
5. **Keep** `affiliate.activated_at` as the canonical "first / most-recent activation" timestamp; do not rename it.
6. **Treat** `AffiliateFraudSignal` as the reference implementation for the per-event-timestamp + per-actor + reason pattern. Use it as the model when adding the new fields in step 1.

The package family is in good shape for a beta-status domain. The fixes above are auditability improvements, not lifecycle restructuring. Every recommended change is additive, nullable, and backward-compatible. No risk to existing data.
