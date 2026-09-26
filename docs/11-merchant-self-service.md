---
title: Merchant Self-Service
---

# Merchant Self-Service

Merchants onboard themselves; the network team approves them. The rule is
absolute: **nothing about an unverified site is marketplace-visible.**

## Lifecycle

1. `RegisterSite` creates the site as `pending`, owned by the registering
   user. Re-registering a domain the same owner had `rejected` resubmits it
   for review; any other duplicate is refused.
2. A network admin verifies, rejects, suspends, or reinstates the site
   (Sites table actions). Verification requires both `status = verified`
   and a `verified_at` timestamp (`AffiliateSite::isVerified()`).
3. Verified merchants submit offers via `SubmitOffer`, which always lands
   as a `draft`. Publishing stays an explicit operator decision.

## Enforcement

Every marketplace-visible transition re-checks `isVerified()`; suspension
cuts a merchant off immediately without touching their data:

| Surface | Unverified-site behavior |
|---|---|
| Offer publish (`CreateOffer` / `UpdateOffer`, admin Activate) | Refused |
| Public listings (`whereSiteVerified` scope) | Excluded |
| Applications (`ApplyToOffer`) | Not found |
| Approvals (`ApproveApplication`) | Refused |
| Link creation + redirects | Refused / 410 |
| Postbacks | 403 |

## Merchant capabilities

Verified merchants manage their own slice through the Filament resources
(`AffiliateOfferResource`, `AffiliateOfferApplicationResource`,
`AffiliateSiteResource`), which are owner-scoped through their `site` relation:
offer drafts, the `requires_approval` toggle (manual review vs auto-approve),
application review for their own offers, per-offer stats, and postback
integration settings (catalog URL + token). Draft terms stay editable; published
terms are operator-locked so live deals cannot change after affiliates join.

> **warning:**
> There is no `AffiliateMerchantService` in this repo, and this package ships
> no merchant-facing portal. `aiarmada/filament-affiliates` ships the
> *affiliate*-facing `Portal*` pages — that is a different actor from a
> network merchant.
