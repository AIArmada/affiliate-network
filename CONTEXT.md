---
title: Affiliate Network Context
package: affiliate-network
status: current
surface: marketplace
family: growth-and-incentives
keywords:
  - marketplace
  - offers
  - sites
  - merchant-network
  - applications
  - conversion-legs
  - merchant-statements
  - creator-postbacks
---

# Affiliate Network Context

## Snapshot
- Composer: `aiarmada/affiliate-network`
- Role: Standalone multi-merchant affiliate marketplace — sites, offers, enrollments, tracking links, an append-only conversion ledger, merchant settlement statements, and outbound creator postbacks. Integrates with `aiarmada/affiliates` only when that package is installed.
- Architecture: Modular Laravel Dune. `src/Platform/` is the package-local core (feature catalog, extension registry, owner scoping, URL/host guards) and never imports concrete features. `src/Features/{Sites,Catalog,Enrollment,Attribution,Ledger,Postbacks}/` each own their Actions, Models, `Api/`, `Queries/`, `EntryPoints/`, and `feature.json` manifest. Cross-feature calls go through `Features/<Name>/Api/*` only.
- Package-wide seams: `src/Contracts/` (pure interfaces), `src/Data/` (immutable enums + `final readonly` DTOs), `src/Drivers/` (contract implementations), `src/Events/`, `src/Exceptions/`, `src/Notifications/`.
- Tooling: `tools/package-check.php` (AST boundary + single-writer enforcement), `tools/package-phpstan.neon` (`PackageOwnedWriteRule`), `tools/architecture.json`, `tests/src/AffiliateNetwork/Arch/PackageArchTest.php`.
- Triggers: marketplace, offers, sites, merchant-network, applications, conversion-legs, statements, postbacks
- Search first: `src/Features, src/Platform, src/Data, src/Contracts, src/Drivers, config, docs`
- Related: `filament-affiliate-network`, `affiliates`, `links`, `checkout`
- Paired: `filament-affiliate-network` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-affiliate-network/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup, migrations, or publishing change

## Guardrails
- Feature boundary: a sibling feature may import only another feature's `Api/` namespace — never its Models, Actions, Queries, or Extensions. `php tools/package-check.php --root=packages/affiliate-network` enforces this.
- Write ownership: every `table:*` is declared in exactly one `feature.json` `owns` entry. Only the listed `Actions/` may mutate it; queries and APIs are read-only. Never bypass via `DB::table(...)`.
- Platform core (`src/Platform/`) is feature-agnostic: it never imports `src/Features/`, `src/Drivers/`, or Eloquent models.
- `src/Contracts/` and `src/Data/` stay pure: interfaces, enums, and `final readonly` DTOs only — no Eloquent, `Illuminate\Http`, features, or drivers.
- Owner scoping: `AffiliateSite` and `AffiliateOfferCategory` use `HasOwner` directly; every other owned model uses `Platform\ScopesByBelongsToOwner` (`offer.site` or `affiliate`, with `offer.site` fallback on links/legs). Cross-tenant writes re-enter the record's own owner with `OwnerContext::withOwner($owner, fn () => ...)` — never rely on ambient request owner.
- If admin UI changes too, audit `filament-affiliate-network` in the same pass.
- Update `docs/*.md` in the same pass when public behavior or config changes.
- Verify with `php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network`, the package PHPStan ruleset, scoped Pint, and the AffiliateNetwork Pest suite.

## Decide fast
- Use when: Marketplace / multi-merchant offers, site verification, enrollment, network money legs, statements, or creator postbacks.
- Skip when: Single-merchant attribution — see `affiliates`.
- Owner/security: Owner-scoped (sites/categories/statements direct; offers/creatives/invitations/deals/coupons relationship-inherited through `offer.site`; applications/links/legs through `affiliate`, with `offer.site` fallback).

## Key surfaces
- Models: `Features/Sites/Models/AffiliateSite`, `Features/Catalog/Models/{AffiliateOffer,AffiliateOfferCategory,AffiliateOfferCreative}`, `Features/Enrollment/Models/{AffiliateOfferApplication,AffiliateOfferInvitation,AffiliateOfferDeal}`, `Features/Attribution/Models/{AffiliateOfferLink,AffiliateOfferCoupon}`, `Features/Ledger/Models/{NetworkConversionLeg,MerchantStatement}`, `Features/Postbacks/Models/{CreatorPostbackEndpoint,CreatorPostbackDelivery}`
- Actions: `Features/Sites/Actions/*`, `Features/Catalog/Actions/*`, `Features/Enrollment/Actions/*`, `Features/Attribution/Actions/*`, `Features/Ledger/Actions/*`, `Features/Postbacks/Actions/*`
- Feature APIs: `SitesApi`, `CatalogApi`, `EnrollmentApi`, `AttributionApi`, `LedgerApi`, `PostbacksApi`
- Queries: `OfferDiscoveryQuery`, `OfferMetricsQuery`, `CreatorBalancesQuery`, `LedgerReconciliationQuery`, `MerchantReceivablesQuery`, `ResolveLegOwnerQuery`, `ApplicationStatusQuery`
- Public seams: `Contracts/{AffiliateIdentityResolver, NetworkLedger, Fulfillment, CatalogReader, DnsResolver, SiteContentClient, SiteVerificationStrategy, CreatorPostbackTransport, NotificationDispatcher}`
- Drivers: `Drivers/Affiliates/*`, `Drivers/UserKeyIdentityDriver`, `Drivers/HttpCreatorPostbackDriver`, `Drivers/HttpSiteContentDriver`, `Drivers/NativeDnsDriver`, `Drivers/RemoteCatalogHttpDriver`
- Extensions: `site.verification_strategy` → `DnsVerificationStrategy`, `FileVerificationStrategy`, `MetaTagVerificationStrategy`
- Config `affiliate-network.php`: `database.{table_prefix,tables,json_column_type}`, `models.affiliate`, `owner`, `currency`, `fees`, `locking`, `statements`, `notifications`, `applications`, `links`, `cookies`, `checkout`, `sync`, `postbacks`, `creator_postbacks`, `http`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-models.md`, `06-services.md`, `07-multi-tenancy.md`, `08-api-reference.md`, `09-testing-factories.md`, `10-merchant-postbacks.md`, `11-merchant-self-service.md`
