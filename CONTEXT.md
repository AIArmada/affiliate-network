---
title: Affiliate Network Context
package: affiliate-network
status: current
surface: marketplace
family: growth-and-incentives
---

# Affiliate Network Context

## Snapshot
- Composer: `aiarmada/affiliate-network`
- Role: Multi-merchant affiliate marketplace with sites, offers, applications, and tracking links.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-affiliate-network`, `affiliates`, `checkout`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-affiliate-network/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns marketplace-domain models, actions, services, integrations, and persistence rules.
- If admin UI changes too, audit `filament-affiliate-network`.
- Update `docs/*.md` in the same pass when public behavior or config changes.
