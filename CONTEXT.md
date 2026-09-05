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
  - tracking-links
---

# Affiliate Network Context

## Snapshot
- Composer: `aiarmada/affiliate-network`
- Role: Multi-merchant affiliate marketplace: sites, offers, applications, tracking links on top of affiliates.
- Triggers: marketplace, offers, sites, merchant-network, applications, tracking-links
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-affiliate-network`, `affiliates`, `checkout`
- Paired: `filament-affiliate-network` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-affiliate-network/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-affiliate-network`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Marketplace / multi-merchant offers and site verification.
- Skip when: Single-merchant attribution — see affiliates.
- Owner/security: Owner-scoped (sites/categories direct; rest relationship-inherited).

## Key surfaces
- Models: `AffiliateOffer`, `AffiliateOfferApplication`, `AffiliateOfferCategory`, `AffiliateOfferCreative`, `AffiliateOfferLink`, `AffiliateSite`
- Actions/Services: `Actions/ApplyToOffer`, `Actions/ApproveApplication`, `Actions/CreateOffer`, `Actions/RecordNetworkConversion`, `Actions/UpdateOffer`, `Services/OfferLinkService`, `Services/OfferManagementService`, `Services/SiteVerificationService`
- Config `affiliate-network.php`: `sites`, `offers`, `offer_categories`, `offer_creatives`, `offer_applications`, `offer_links`, `database`, `table_prefix`, `tables`, `json_column_type`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-models.md`, `06-services.md`, `07-multi-tenancy.md`, `08-api-reference.md`, `09-testing-factories.md`
