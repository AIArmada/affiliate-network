## Second pass — 2026-06-09

### Confirmed
- Phase 1: 5 Actions created: `ApplyToOffer`, `ApproveApplication`, `CreateOffer`, `RecordNetworkConversion`, `UpdateOffer`. Migrated out of services.
- Phase 2: `Contracts/SiteVerificationStrategyInterface` exists. 3 implementations: `DnsVerificationStrategy`, `FileVerificationStrategy`, `MetaTagVerificationStrategy`. `SiteVerificationService` is coordinator.
- Phase 3: `OfferLinkService` (URL signing, redirect, click attribution) and `OfferManagementService` (offer lifecycle) boundary is explicit and documented.
- Phase 4: 5 Events created: `ApplicationApproved`, `ApplicationSubmitted`, `NetworkConversionRecorded`, `OfferCreated`, `OfferUpdated`. All Actions dispatch events correctly (verified in source). Signals/affiliates listeners updated.

### Still open
- **No Console/Commands directory**: ✅ Resolved in Phase 5 — `Console/Commands/` added with `ArchiveExpiredOffersCommand` integrated with `OwnerBatchRunner`.
- **Cookie tracking overlap with affiliates**: ✅ Resolved in Phase 7 — audit confirmed `TrackNetworkLinkCookie` and `AttachAffiliateFromCookie` operate on different data shapes and cookie names. Not duplicates. No extraction needed.

### New findings
1. **Actions are clean but thin**: The 5 Actions are mostly write-through to models with event dispatch. No complex orchestration yet. This is appropriate for the package's current scope but means Actions don't yet prove their value as reusable workflow units.
2. **Site verification strategy is well-implemented**: Each strategy handles one verification method (DNS TXT record, HTML file presence, meta tag parsing). The coordinator pattern in `SiteVerificationService` is correct — resolves strategies per method and aggregates results.
3. **OfferLinkService and OfferManagementService boundary holds**: No overlap found. Link service handles URL construction (signed routes, parameter encoding) and click analytics. Management service handles CRUD, category assignment, and status transitions. The split is clear and maintainable.
4. **Model count (6) is lean**: No model proliferation since original review. `AffiliateOffer`, `AffiliateOfferApplication`, `AffiliateOfferCategory`, `AffiliateOfferCreative`, `AffiliateOfferLink`, `AffiliateSite` — each maps to a clear domain concept.
5. **No `Exceptions/` directory**: The package has no custom exceptions. Errors bubble up as generic exceptions. Consider adding domain exceptions (`OfferNotFoundException`, `ApplicationAlreadySubmittedException`, `SiteVerificationFailedException`) as the package matures.

### Updated recommendation
The package is the cleanest of the 7 audited — all [done] items are genuinely done. When the first batch operation is added, create `Console/Commands/` and integrate with `OwnerBatchRunner`. Extract shared cookie tracking to commerce-support when affiliates confirms the same need. Add domain exceptions for the 3-4 most common error paths.

---

# Affiliate Network friendliness review

This note reviews `packages/affiliate-network` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (3 classes)
- `src/Http/Controllers` (1 controller)
- `src/Http/Middleware` (1 middleware)
- `src/Listeners` (1 listener)
- `src/Models` (6 classes)
- `routes/api.php`
- downstream consumers in `affiliates`, `checkout`, `orders`

## What is already friendly

### Service split is clear

- `Services/OfferLinkService.php`
- `Services/OfferManagementService.php`
- `Services/SiteVerificationService.php`

The three services each own a distinct concern. This is a clean split.

### Models are organized

- 6 models: `AffiliateOffer`, `AffiliateOfferApplication`, `AffiliateOfferCategory`, `AffiliateOfferCreative`, `AffiliateOfferLink`, `AffiliateSite`

The model surface is clear.

### Route is signed and minimal

- `routes/api.php` — `GET /affiliate-network/go/{code}` (signed, invokable controller)

The single route is well-scoped.

### Listener reacts to order events

- `Listeners/RecordNetworkConversionForOrder.php`

The package reacts to `OrderPaid`/`OrderRefunded` through a listener, not by reaching into orders.

## Findings

### 1. There is no `Actions/` directory

**Why this hurts friendliness**

The package has services but no Actions. All mutations (create offer, apply offer, record conversion) likely live inline in services. The monorepo rule is "Actions only."

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/CreateOffer`
- `Actions/UpdateOffer`
- `Actions/ApplyToOffer`
- `Actions/ApproveApplication`
- `Actions/RecordNetworkConversion`

The three services become either thin orchestrators or read-side (queries, lookups, verification).

### 2. `SiteVerificationService` is the right shape but the seam is unclear

**Files**

- `src/Services/SiteVerificationService.php`

**Why this hurts friendliness**

Site verification (proving the affiliate owns the site) is a variant-heavy area. Different verification methods (DNS, HTML file, meta tag) will need their own implementations. The current shape may be a single class with branching.

**Recommendation**

Extract a `SiteVerificationStrategyInterface` and one implementation per method. The service coordinates the strategies.

### 3. `OfferLinkService` and `OfferManagementService` overlap on the offer model

**Files**

- `src/Services/OfferLinkService.php`
- `src/Services/OfferManagementService.php`

**Why this hurts friendliness**

The two services likely both touch the `AffiliateOffer` and `AffiliateOfferLink` models. The split between "link" and "management" is unclear.

**Recommendation**

Audit both. The link service should focus on URL signing, redirect handling, and click attribution. The management service should focus on offer lifecycle. Make the boundary explicit.

### 4. Listener is a single class — this is the right shape

**Files**

- `src/Listeners/RecordNetworkConversionForOrder.php`

**Why this is worth noting**

A focused listener that reacts to order events. Keep this discipline.

### 5. Middleware is package-local

**Files**

- `src/Http/Middleware/TrackNetworkLinkCookie.php`

**Why this hurts friendliness**

The middleware tracks link clicks via cookies. If the same cookie-tracking pattern is needed elsewhere (affiliates, signals), it may be duplicated.

**Recommendation**

Audit `TrackNetworkLinkCookie` against the affiliates' cookie attribution. If they overlap, extract a shared cookie helper to `commerce-support`.

### 6. The `AffiliateNetworkServiceProvider` is small

**Files**

- `src/AffiliateNetworkServiceProvider.php`

**Why this is worth noting**

The provider is currently lean. Use the monorepo's tagged-registrar pattern as strategies multiply.

### 7. No `Events/` directory

**Why this hurts friendliness**

The package consumes order events but may not produce its own. Downstream packages (affiliates, signals, dashboard) may need to react to offer lifecycle.

**Recommendation**

Add events:

- `Events/OfferCreated`
- `Events/OfferUpdated`
- `Events/ApplicationSubmitted`
- `Events/ApplicationApproved`
- `Events/NetworkConversionRecorded`

### 8. No `Console/Commands` directory

**Why this hurts friendliness**

Bulk operations (offer archival, application review queues, conversion reconciliation) have no clear owner.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/CreateOffer`, `UpdateOffer`, `ApplyToOffer`, `ApproveApplication`, `RecordNetworkConversion`.
2. Move orchestration out of services.
3. Add tests for each Action.

### Phase 2 — extract site verification strategy

**Steps**

1. Add `Contracts/SiteVerificationStrategyInterface`.
2. Add DNS, HTML file, meta tag implementations.
3. Make `SiteVerificationService` a coordinator.

### Phase 3 — audit offer link vs management services

**Steps**

1. Audit `OfferLinkService` and `OfferManagementService`.
2. Make the boundary explicit.
3. Document the split.

### Phase 4 — add domain events

**Steps**

1. Add the missing events.
2. Dispatch from the new Actions.
3. Update signals/affiliates listeners.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [done] Add `src/Actions/CreateOffer`, `UpdateOffer`, `ApplyToOffer`, `ApproveApplication`, `RecordNetworkConversion`.
- [done] Move orchestration out of services.
- [done] Add tests for each Action.

### Phase 2 — extract site verification strategy

- [done] Add `Contracts/SiteVerificationStrategyInterface`.
- [done] Add DNS, HTML file, meta tag implementations.
- [done] Make `SiteVerificationService` a coordinator.

### Phase 3 — audit offer link vs management services

- [done] Audit `OfferLinkService` and `OfferManagementService`.
- [done] Make the boundary explicit.
- [done] Document the split.

### Phase 4 — add domain events

- [done] Add the missing events.
- [done] Dispatch from the new Actions.
- [done] Update signals/affiliates listeners.

### Phase 5 — prepare for batch operations

- [done] Add `Console/Commands/` directory with `ArchiveExpiredOffersCommand`.
- [done] Integrate new batch commands with `OwnerBatchRunner` from `commerce-support`.

### Phase 6 — add domain exceptions

- [done] Create `Exceptions/` directory with domain-specific exception classes.
- [done] Add `OfferNotFoundException` exception (with `withId()` and `withCode()` factory methods).
- [done] Add `ApplicationAlreadySubmittedException` exception (with `forOffer()` factory method).
- [done] Add `SiteVerificationFailedException` exception (with `methodFailed()` and `noMethodsRemaining()` factory methods).
- [done] Update Action and Service code to throw domain exceptions instead of generic exceptions.
    **Result:** `ApplyToOffer::execute()` now throws `ApplicationAlreadySubmittedException` (via `forOffer()`) instead of `RuntimeException` for rejected reapplication. Model concern `RuntimeException` throws in `ScopesBySiteOwner` and `ScopesByAffiliateOwner` are owner-scope guardrails — appropriate as generic exceptions, no domain-specific equivalent needed.

### Phase 7 — extract shared cookie tracking

- [done] Audit `TrackNetworkLinkCookie` against affiliates' `AttachAffiliateFromCookie` for duplication.

**Audit result:** `TrackNetworkLinkCookie` tracks network link codes (`anl` parameter) and stores JSON metadata (link code, affiliate_id, offer_id, clicked_at). Affiliates' `AttachAffiliateFromCookie` tracks affiliate codes (`aff` parameter) and stores simple string codes. They operate on different data shapes and cookie names. Not duplicates.

- [done] If shared cookie-attribution abstraction is needed in the future, extract `commerce-support` helper. Current surface area doesn't justify it. (Evaluated and deferred — no action needed.)



## Suggested verification scope

- per-Action tests
- service tests
- controller and middleware tests
- cross-package tests for affiliates/checkout/orders

## Recommended first move

Phase 1 — introduce the Actions tree. The package has services but no Actions, and the monorepo rule is consistent. The split is mostly mechanical and unblocks the strategy and boundary audits.
