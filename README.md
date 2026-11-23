# Affiliate Network

Standalone multi-merchant affiliate network and marketplace for Laravel.

## Overview

This package runs on its own so merchants can publish offers and creators can
discover, apply, and promote them. Install `aiarmada/affiliates` alongside it to
light up merchant identity, the conversion ledger, and catalog mirroring through
the network seams.

- **Merchants** register sites, verify domains, create offers, configure
  settlement and prepaid/spend caps, and reconcile statements.
- **Creators** discover offers, apply or accept invitations, redeem custom deals
  and promo coupons, and generate deep tracking links.
- **Network operators** verify sites, approve applications, run the money ledger,
  issue merchant statements, and configure outbound creator postbacks.

The package follows a **Modular Laravel Dune** layout: six features
(`Sites`, `Catalog`, `Enrollment`, `Attribution`, `Ledger`, `Postbacks`) each own
their writes, with cross-feature access only through `Features\*\Api\*`.

## Installation

```bash
composer require aiarmada/affiliate-network
```

Optionally require the engine to enable local identity, ledger fulfillment, and
catalog mirroring:

```bash
composer require aiarmada/affiliates
```

Publish config and migrate:

```bash
php artisan vendor:publish --tag=affiliate-network-config
php artisan migrate
```

## Key Concepts

### Sites
Merchant domains with DNS / meta-tag / file verification, a rotating catalog
token, an optional catalog URL for remote mirroring, settlement settings
(`default_locking_days`, `require_settled_for_payout`), and prepaid/spend caps
(`prepaid_balance_minor`, `monthly_spend_cap_minor`).

### Offers
Products or services creators promote. Offers carry commission terms (basis
points, fixed amount, volume tiers, per-event CPA), visibility (`public`,
`private`, `unlisted`), caps, fallback URLs/offers, active promotions, traffic
restrictions, and a per-offer locking window.

### Enrollment
Creators apply (`ApplyToOffer`), accept private invitations (`AcceptOfferInvitation`),
or receive per-affiliate custom deals (`UpsertOfferDeal`). The network owns this
enrollment flow end to end; it never touches merchant program memberships.

### Deep Links & Coupons
Approved creators generate unsigned public tracking links through
`aiarmada/links` (`/go/{slug}`). Linkless promotion is supported through
affiliate-assigned promo coupon codes that attribute conversions without a
click.

### Money Legs
Every conversion posts one append-only `NetworkConversionLeg`: revenue,
commission, network fee, and payout in minor units, plus the pricing tier and
event type. Balances derive from legs at read time; reversals post negated
companion legs; partial refunds post adjustment companions.

### Statements & Postbacks
`MerchantStatement` aggregates posted legs per site/currency/period and moves
`draft → issued → paid` (or `void`). Outbound creator S2S postbacks deliver
signed HMAC-SHA256 events for recorded, locked, reversed, and adjusted
conversions.

## Marketplace Capabilities

1. **Validation locking window** — posted legs can mature through `LockMaturedLegs` instead of paying instantly.
2. **Merchant statements & prepaid/spend caps** — period statements plus site-level budget enforcement.
3. **Private/unlisted invitations & per-affiliate deals** — invite-only offers with tokenized invitations and rate overrides.
4. **Linkless promo coupons** — coupon codes assigned per affiliate and resolved at conversion time.
5. **Outbound creator S2S postbacks & sub IDs** — signed callbacks with `sub1`/`sub2`/`sub3` propagation.
6. **Offer caps & smart fallback redirects** — daily/monthly/total caps with `fallback_url`/`fallback_offer_id`.
7. **Partial refund adjustments & multi-event CPA** — adjustment legs and `event_rates` for signup/lead/purchase events.
8. **Marketplace discovery & metrics** — `OfferDiscoveryQuery` and `OfferMetricsQuery` for campaigns and reports.
9. **Enforced restrictions & active promotions** — country/channel/device/order restrictions and time-boxed promotions applied at posting.

## Documentation

See [docs/](docs/) for complete documentation, starting with the
[overview](docs/01-overview.md).
