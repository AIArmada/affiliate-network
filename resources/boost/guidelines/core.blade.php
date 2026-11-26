@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Affiliate Network

`aiarmada/affiliate-network` is a standalone multi-merchant affiliate marketplace. It follows a **Modular Laravel Dune** layout: six features own their writes, and cross-feature access happens only through `Api/` interfaces.

### Feature boundaries

| Feature (`AIArmada\AffiliateNetwork\Features\<Name>`) | Owns |
| --- | --- |
| `Sites` | `affiliate_network_sites` — verification, catalog tokens, prepaid balance |
| `Catalog` | `affiliate_network_{offers,offer_categories,offer_creatives}` — offers, creatives, sync, discovery, metrics |
| `Enrollment` | `affiliate_network_{offer_applications,offer_invitations,offer_deals}` — applications, invitations, deals |
| `Attribution` | `affiliate_network_{offer_links,offer_coupons}` — links, coupons, counters |
| `Ledger` | `affiliate_network_{conversion_legs,merchant_statements}` — legs, caps, locking, statements |
| `Postbacks` | `affiliate_network_{creator_postbacks,postback_deliveries}` — creator webhooks |

Rules:

- Only `Features\<Name>\Api\*` may cross a feature boundary. Never import another feature's `Models`, `Actions`, `Queries`, or `Extensions`.
- Writes go through `Features\<Feature>\Actions\*` via `app(Action::class)->execute(...)`. Reads go through `Features\<Feature>\Queries\*` or `Features\<Feature>\Api\*`.
- `src/Platform/` is feature-agnostic; it never imports `Features/` or `Drivers/`.
- `src/Contracts/` and `src/Data/` are pure: interfaces, backed enums, and `final readonly` DTOs only.

### Core conventions

- UUID primary keys (`HasUuids`); tables resolve through `getTable()` from `affiliate-network.database.tables`, never a literal `$table`.
- Use `CarbonImmutable` for every timestamp.
- Money is integer minor units plus an explicit ISO currency; format with `AIArmada\CommerceSupport\Support\MoneyFormatter`.
- Owner scoping uses `Platform\ScopesByBelongsToOwner` (relationship path `site`, `offer.site`, or `affiliate` with `offer.site` fallback) or `HasOwner` for direct rows.
- Cross-owner writes re-enter the record's owner with `OwnerContext::withOwner($owner, fn () => ...)`. Never trust ambient request owner for postbacks, leg maturation, or prepaid adjustments.
- Affiliate-facing writes lock the canonical affiliate row through `AffiliateIdentityResolver::lockForWrite()` before touching network tables. Creator postback delivery is lease-based and the transport call runs outside the database transaction.
- Conversions are idempotent on `(link|coupon, external_reference, event_type)`. `RecordNetworkConversion` takes scalar IDs, never an Eloquent link model.
- Events are `final readonly` and carry scalars/DTOs — never Eloquent models.

### Shortest supported write path

@verbatim
<code-snippet name="Record a network conversion through the owning Action" lang="php">
declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;

$leg = app(RecordNetworkConversion::class)->execute(
    offerId: $offerId,
    affiliateId: $affiliateId,
    revenueMinor: 5999,
    currency: 'USD',
    externalReference: 'ORDER-123',
    eventType: 'purchase',
    linkId: $linkId,
    subId: 'blog-post-1',
);

$leg instanceof NetworkConversionLeg; // true
</code-snippet>
@endverbatim

### Extension slot

Verification strategies live in `Features\Sites\Extensions\*` and register under `site.verification_strategy` (`dns`, `meta_tag`, `file`). Implement `Contracts\SiteVerificationStrategy` to add one; resolve through `VerifySiteByStrategy`.

### Verification

Run these before declaring done:

```bash
php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network
./vendor/bin/phpstan analyse -c packages/affiliate-network/tools/package-phpstan.neon --autoload-file=packages/affiliate-network/tools/Architecture/PackageOwnedWriteRule.php --no-progress
./vendor/bin/pint packages/affiliate-network
./vendor/bin/pest --parallel tests/src/AffiliateNetwork
```

Treat `package-check.php` violations and PHPStan errors as build failures; do not add suppressions or `withoutOwnerScope()` shortcuts.
@endif
