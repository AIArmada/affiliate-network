---
title: Troubleshooting
---

# Troubleshooting

## Site verification

### DNS verification fails

- Wait for propagation (`dig TXT yourdomain.com +short`).
- The TXT value must equal `$site->verification_token` exactly.
- Some providers require the record name `@` or the bare domain.
- `VerifySiteByStrategy::execute()` **throws** on failure; it never returns `false`.

### Meta-tag / file verification fails

- The site must serve over HTTPS.
- Meta tag must be `<meta name="affiliate-network-verify" content="…">` in `<head>`.
- File must be at `/.well-known/affiliate-network-verify.txt` with exactly the
  token (no trailing whitespace) and the directory reachable.

### "Only pending sites can be verified."

`VerifySite` only transitions `pending → verified` (a no-op when already
verified). Suspended sites are restored with `ReinstateSite`, not `VerifySite`.
Rejected sites must be resubmitted by the owner (`RegisterSite`) or reset by an
operator.

## Merchant postbacks

### `401 Invalid site credentials`

- `site` must be the site ID or the exact domain (case-insensitive).
- The bearer token must be the current catalog token. Rotation invalidates the
  previous token immediately; re-issue and redeploy.
- Tokens are stored encrypted; a rotated/missing token fails closed.

### Postgres UUID vs domain lookup

Site identifiers are UUID primary keys. `SitesApi::findByIdOrDomainUnscoped()`
uses `Str::isUuid()` to decide between `whereKey()` and a `domain` lookup:

```php
// Domain path (lowercased):
$sites->findByIdOrDomainUnscoped('merchant.example');

// UUID path:
$sites->findByIdOrDomainUnscoped('9b1e…-…');
```

> **warning**
> Never call `findByIdUnscoped()` with a domain. Postgres rejects
> `where id = 'merchant.example'` on a `uuid` column with
> `invalid input syntax for type uuid`. Use `findByIdOrDomainUnscoped()` on any
> untrusted `site` payload.

### Cross-owner postback writes

Postbacks arrive with no ambient owner. The ledger actions re-enter the
record's owner:

- `RecordNetworkConversion` derives the owner from the affiliate identity.
- `AdjustConversionLeg` and `LockMaturedLegs` use `ResolveLegOwnerQuery`.
- Standalone installs (no affiliate identity owner) run in global context.

If a write throws a cross-owner `RuntimeException`, confirm the site/affiliate
identity is resolvable and that `owner.enabled` matches how the rows were
created. Do not "fix" it by running the action under a different ambient owner —
resolve the record first.

### `422 affiliate_profile_required`

The Affiliates identity adapter is bound and owner mode is on, but the affiliate
id has no merchant `Affiliate` row. Complete affiliate registration or (in a
combined install with both owner settings disabled) use the user-key fallback.

### `410 Offer was not active at the click time`

`CatalogApi::wasOfferActiveAt()` checks `published_at`/`archived_at`/`starts_at`/
`ends_at` at the reported `network_clicked_at`. Send the true server-observed
click instant, not the report time.

### Pay-when-paid holds

With `site.require_settled_for_payout = true`, automatic fulfillment is skipped.
Posted legs stay `payable` until `MarkMerchantStatementPaid` fulfils them. This
is expected, not a bug.

## Tracking links

### `404` for `/go/{slug}`

- Redirects are owned by `aiarmada/links`; verify `php artisan route:list | grep links.redirect`.
- Unknown slugs return `404`; policy-blocked links return `410` (or fall back
  when a fallback URL exists).
- Clear route cache: `php artisan route:clear`.

### `410` for inactive offer / unverified site / missing approval

The `OfferLinkGate` blocks when:

- the link or backing link is inactive/expired,
- the offer is inactive at redirect time,
- the site is not verified,
- the offer requires approval and the affiliate is not approved.

It falls back to `offer.fallback_url`, then `offer.fallback_offer_id`, then
`links.default_fallback_url` when configured; otherwise `410`.

### Link target rejected

`CreateOfferLink` enforces `Platform\DomainHostValidator::isAllowedTargetUrl()`:
the target must be HTTP(S), have no credentials, and resolve to the verified
domain or a subdomain. This prevents open redirects.

> **warning**
> `allow_deep_linking = false` on an offer rejects any explicit `target_url`.
> Drop the option or enable deep linking.

### Branded links broke after a rename

Branded URLs snapshot the creator handle at issue time. Renaming a creator does
not rewrite issued links, and both URL segments must match the stored link.
Issue new links instead of editing old ones.

## Conversions and money

### Conversion revenue is skipped

Symptom: `conversions` increments but `revenue` does not, with a
`currency_mismatch` log. Cause: the conversion currency differs from the link's
pricing currency. The leg keeps reported revenue for audit but earns zero
commission/fee/payout. Pass the correct currency or align offer currency.

### Duplicate conversion not recorded

Reporting is idempotent on `(link|coupon, external_reference, event_type)`. A
repeat returns `duplicate: true` with the original leg. If you intended a second
leg, use a distinct `event_type` or reference.

### Adjustment rejected

- `422` for terminal/companion legs: only `pending`/`posted` originals adjust.
- A zero `delta_revenue_minor` is invalid.
- Adjustments are idempotent on `adj:{legId}:{reference}`; reuse the same
  reference to fetch, not to create a second adjustment.

### Creator balance looks wrong

Balances derive from legs at read time. `superseded` and `reversed` legs never
enter `payableMinor`. A reversal companion of a fulfilled leg appears in
`netPayableMinor` as a clawback offset. Run reconciliation before assuming
counters drifted.

## Caps and restrictions

### `CapExceededException`

Reasons: `daily_conversion_cap`, `monthly_conversion_cap`,
`total_conversion_cap`, `per_affiliate_conversion_cap`,
`monthly_payout_cap_minor`, `total_payout_cap_minor`,
`site_monthly_spend_cap_minor`, `site_prepaid_balance`. Inspect the offer caps
and the site's `prepaid_balance_minor`/`monthly_spend_cap_minor`.

### `RestrictionViolatedException`

Check the offer's `restrictions` (country/channel/device/order) against the
`RestrictionContext` sent on apply/redirect/postback.

## Creative assets

### SVG uploads rejected

`AffiliateOfferCreative::registerMediaCollections()` accepts only
`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `video/mp4`, `video/webm`,
`application/pdf`, and `application/zip`. `image/svg+xml` is intentionally
excluded (SVG can carry script). Convert to PNG/WebP.

### `getAssetUrl()` returns null

Manual creatives need a file in the single-file `creative_asset` collection on
the `public` disk. Imported creatives need `source_asset_url`. With neither,
the URL is null.

## HTTP and SSRF

### Outbound HTTP blocked

`PublicHttpUrlGuard` / `DomainHostValidator` reject private/reserved IPs,
`localhost`, `*.local`/`*.internal`, non-HTTP(S) schemes, credentials in the
URL, fragments, and non-standard ports. Catalog pulls, site-content fetches, and
creator postbacks all use this guard; it cannot be disabled via config. Use a
public DNS name with a standard port.

### Response too large

`http.max_response_bytes` (default 1 MiB) caps catalog, site-content, and
postback response bodies. A larger body yields a transport error / `response_too_large`
rather than truncation.

## Multi-tenancy

### Cross-tenant data visible

- Verify `AFFILIATE_NETWORK_OWNER_ENABLED=true`.
- Confirm `OwnerResolverInterface` is bound.
- Ensure your reads go through owner-scoped models/APIs, not `DB::table()`.
- `AffiliateOfferApplication` scopes through `offer.site`; a query expecting
  affiliate-based scoping should use `EnrollmentApi` instead.

### `RuntimeException` on create/update/delete

"Cannot create, update, or delete {Model} without an accessible owner relation."
The foreign relation points at a row owned by another tenant. Resolve the parent
inside the current owner context or create the child through the owning action.

## JSON columns

### PostgreSQL JSON errors

Set `AFFILIATE_NETWORK_JSON_COLUMN_TYPE=jsonb` (default) or `json` for engines
without `jsonb`. Never hardcode the type in a migration.

## Events

### Readonly event errors

Every event is `final readonly` and carries scalars/DTOs only. Do not add
`InteractsWithSockets`, mutable properties, or Eloquent models. If you need
broadcastable payloads, create a separate event that wraps the immutable one.

### `NetworkConversionRecorded` fired but nothing paid

The event signals a recorded leg, not a payable one. Check
`$event->leg->status->paysOut()` and whether the site requires settlement before
payout.

## Debugging

```php
DB::enableQueryLog();
$sites = AffiliateSite::all();
dd(DB::getQueryLog());
```

```php
$model = new AffiliateSite();
dd($model->getGlobalScopes());
```

```php
app(\AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi::class);
app(\AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi::class);
app(\AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi::class);
```

```bash
php artisan affiliate-network:reconcile --offer={offerId} --fail-on-mismatch
php artisan affiliate-network:lock-matured
php artisan logs | tail -100
```

## Getting help

1. Check [configuration](03-configuration.md) and [usage](04-usage.md).
2. Run the package verification suite (see [testing](09-testing-factories.md)).
3. Open an issue with PHP/Laravel versions, package version, full error, and
   reproduction steps.
