---
title: Merchant Self-Service
---

# Merchant Self-Service

Merchants onboard themselves; the network team approves them. The rule is
absolute: **nothing about an unverified site is marketplace-visible.**

## Lifecycle

1. `RegisterSite` creates the site as `pending`, owned by the registering
   user/tenant. Re-registering a domain the same owner had `rejected` resubmits
   it for review; any other duplicate is refused.
2. The network verifies the domain with DNS, meta-tag, or file verification.
   `AffiliateSite::isVerified()` requires both `status = verified` **and** a
   `verified_at` timestamp.
3. Verified merchants submit offers via `SubmitOffer`, which always lands as a
   `draft`. Publishing stays an explicit operator decision.
4. Operators attach settlement settings and run statements as orders settle.

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RegisterSite;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SubmitOffer;

$site = app(RegisterSite::class)->execute($owner, [
    'name' => 'My Store',
    'domain' => 'mystore.com',
]);

$siteRecord = app(SitesApi::class)->findById((string) $site->getKey());

$offer = app(SubmitOffer::class)->execute($siteRecord, [
    'name' => 'Spring campaign',
    'rate_base_bp' => 1000,
    'requires_approval' => true,
]);
```

## Domain verification

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\GenerateVerificationToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySiteByStrategy;

$token = app(GenerateVerificationToken::class)->execute($site);

$instructions = app(VerifySiteByStrategy::class)->getInstructions($site, 'dns');
// ['title' => 'DNS TXT Record', 'record_type' => 'TXT', 'record_value' => 'affiliatenetwork-verify-…', ...]

$verified = app(VerifySiteByStrategy::class)->execute($site, 'dns');
```

| Method | Placement |
|---|---|
| `dns` | TXT record on the domain |
| `meta_tag` | `<meta name="affiliate-network-verify" content="…">` in `<head>` |
| `file` | `https://domain/.well-known/affiliate-network-verify.txt` containing the token |

Methods come from the `site.verification_strategy` extension slot. Unknown
methods throw; a failed check throws (it does not silently return `false`).

Changed domains reset a verified site to `pending`, clear `verified_at`, and
mint a fresh verification token.

## Catalog token issuance and rotation

The catalog token is the shared secret for catalog pulls **and** merchant
postbacks. It is stored encrypted and shown plaintext only once.

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\IssueCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;

$token = app(IssueCatalogToken::class)->execute($site);     // first issue
$fresh = app(RotateCatalogToken::class)->execute($site);    // rotate; previous dies immediately
```

`AffiliateSite::matchesCatalogToken(?string $token)` performs the constant-time,
fail-closed comparison used by every merchant endpoint.

## Catalog sync

Merchants with a remote catalog set `catalog_url` + `catalog_token_encrypted`
and sync through `SyncSiteOffers`:

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncSiteOffers;

$result = app(SyncSiteOffers::class)->sync($siteRecord, $programId);
$result = app(SyncSiteOffers::class)->syncAll($siteRecord);
```

An empty `catalog_url` means an owned, shared-DB site that reads the engine
catalog directly. Sync status lands on `sync_status` (`never`, `ok`, `partial`,
`failed`) and `last_synced_at`. Rate edits flip an offer to `manual`; later
syncs hold those rates back (`locked`).

## Settlement and locking configuration

Merchants (or operators acting for them) configure settlement on the site:

```php
$site = app(\AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite::class)->execute($site, [
    'default_locking_days' => 30,             // offer.locking_days wins when set
    'require_settled_for_payout' => true,     // pay only after statement settlement
    'prepaid_balance_minor' => 500_000,       // null = no prepaid limit
    'budget_currency' => 'USD',
    'monthly_spend_cap_minor' => 200_000,     // null = no monthly cap
]);
```

- `default_locking_days` — the fallback lock window; `offer.locking_days` overrides it, then `locking.default_days`.
- `require_settled_for_payout` — the pay-when-paid gate. When true, `MarkMerchantStatementPaid` fulfils attached legs and automatic conversion fulfillment is skipped.
- `prepaid_balance_minor` — debited by posted conversions and adjustments; a nil balance means unlimited. `CheckOfferCaps` blocks when the balance cannot cover the proposed payout.
- `monthly_spend_cap_minor` — blocks new commissions once the site's month-to-date spend is reached.
- `budget_currency` — fallback pricing currency when an offer has none.

Adjust prepaid balance explicitly:

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\AdjustMerchantPrepaidBalance;

$balance = app(AdjustMerchantPrepaidBalance::class)->execute($siteId, 100_000);
```

## Statement lifecycle

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\GenerateMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;

$statement = app(GenerateMerchantStatement::class)->execute(
    $siteId, 'USD', $periodStart, $periodEnd,
); // draft

$statement = app(IssueMerchantStatement::class)->execute((string) $statement->getKey());
$statement = app(MarkMerchantStatementPaid::class)->execute((string) $statement->getKey(), 'BANK-REF');
$statement = app(VoidMerchantStatement::class)->execute((string) $statement->getKey());
```

| Status | Meaning | Allowed next |
|---|---|---|
| `draft` | Aggregated legs, not sent | issue, void, mark paid |
| `issued` | Sent, `due_at` stamped | void, mark paid |
| `paid` | Settled, `payment_reference` stored | terminal |
| `void` | Cancelled, legs detached | terminal |

Only posted, unstated, non-companion legs attach to a statement. Issuing stamps
`issued_at` and `due_at` (`statements.due_days`). Voiding detaches attached legs
so a later statement can claim them.

## Enforcement

Every marketplace-visible transition re-checks `isVerified()`; suspension cuts a
merchant off immediately without touching their data:

| Surface | Unverified-site behavior |
|---|---|
| Offer publish (`CreateOffer`/`UpdateOffer`, admin Activate) | Refused |
| Public listings (`whereSiteVerified` scope) | Excluded |
| Applications (`ApplyToOffer`) | Not found |
| Approvals (`ApproveApplication`) | Refused |
| Link creation + redirects | Refused / `410` |
| Merchant postbacks | `403` |

## Merchant capabilities

Verified merchants manage their slice through the Filament resources
(`AffiliateSiteResource`, `AffiliateOfferResource`,
`AffiliateOfferApplicationResource`), which are owner-scoped through their
relationships: site settings and tokens, offer drafts, the
`requires_approval` toggle, application review for their own offers, per-offer
stats, and postback integration settings (catalog URL + token).

> **warning**
> There is no merchant-facing portal in this package.
> `aiarmada/filament-affiliates` ships the *affiliate*-facing `Portal*` pages —
> a different actor from a network merchant. The admin-only
> `MerchantStatementResource` is not a merchant surface.
