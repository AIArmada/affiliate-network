---
title: Testing & Factories
---

# Testing & Factories

All thirteen models ship factories under
`AIArmada\AffiliateNetwork\Database\Factories`. Each model binds its factory via
`newFactory()`, so `Model::factory()` works without configuration.

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;

$offer = AffiliateOffer::factory()->published()->create();
```

## AffiliateSiteFactory

`Features\Sites\Models\AffiliateSite::factory()`

| State | Effect |
|---|---|
| `pending()` | `SiteStatus::Pending`, no verification |
| `verified()` | `SiteStatus::Verified`, `verified_at` set |
| `suspended()` | `SiteStatus::Suspended` (retains `verified_at`) |
| `rejected()` | `SiteStatus::Rejected` |
| `forOwner($owner)` | Sets `owner_type`/`owner_id` |
| `withSettings(array $settings)` | Sets `settings` |

```php
$site = AffiliateSite::factory()->verified()->create([
    'default_locking_days' => 30,
    'prepaid_balance_minor' => 500_000,
    'budget_currency' => 'USD',
    'monthly_spend_cap_minor' => 200_000,
    'require_settled_for_payout' => true,
]);
```

## AffiliateOfferCategoryFactory

`Features\Catalog\Models\AffiliateOfferCategory::factory()`

`active()`, `inactive()`, `forParent($parent)`, `forOwner($owner)`,
`sortOrder(int)`, `withIcon(string)`.

```php
$parent = AffiliateOfferCategory::factory()->create();
$child = AffiliateOfferCategory::factory()->forParent($parent)->sortOrder(2)->create();
```

## AffiliateOfferFactory

`Features\Catalog\Models\AffiliateOffer::factory()`

| State | Effect |
|---|---|
| `draft()` / `published()` / `archived()` | `OfferStatus` |
| `featured()` / `private()` / `unlisted()` | Marketplace flags |
| `autoApprove()` | `requires_approval = false` |
| `forSite($site)` / `forCategory($category)` | Relations |
| `flatRate(int $amountMinor = 500)` / `percentage(int $rateBps = 1000)` | Pricing |
| `withDateRange($startsAt, $endsAt)` | Campaign window |
| `withLockingDays(int $days = 30)` | Locking window |
| `withCaps(?daily, ?monthly, ?total, ?perAffiliate, ?monthlyPayoutMinor, ?totalPayoutMinor)` | Caps |
| `withFallbackUrl(string)` / `withFallbackOffer($offer)` | Redirect fallback |
| `withEventRates(array $rates)` | Multi-event CPA |
| `withRestrictions(array $restrictions)` | Traffic restrictions |
| `withPromotions(array $promotions)` | Active promotions |

```php
$offer = AffiliateOffer::factory()
    ->forSite($site)
    ->published()
    ->percentage(1500)
    ->withLockingDays(30)
    ->withCaps(daily: 100, totalPayoutMinor: 1_000_000)
    ->withEventRates(['purchase' => 1000, 'signup' => 500])
    ->create();
```

> **warning**
> There are no `pending()`, `active()`, `paused()`, or `expired()` states.
> `OfferStatus` has exactly three cases.

## AffiliateOfferCreativeFactory

`Features\Catalog\Models\AffiliateOfferCreative::factory()`

`banner(int $w = 728, int $h = 90)`, `text()`, `email()`, `html()`, `video()`,
`image(int $w = 1200, int $h = 628)`, `document()`, `withDestination(string)`,
`withAsset(string)`, `active()`, `inactive()`, `forOffer($offer)`,
`sortOrder(int)`.

## AffiliateOfferApplicationFactory

`Features\Enrollment\Models\AffiliateOfferApplication::factory()`

`pending()`, `approved()`, `rejected(string $reason = 'Does not meet requirements')`,
`revoked(string $reason = 'Terms of service violation')`, `forOffer($offer)`,
`forAffiliateId(string)`, `withReason(string)`.

```php
$application = AffiliateOfferApplication::factory()
    ->forOffer($offer)
    ->forAffiliateId((string) Str::uuid())
    ->approved()
    ->create();
```

## AffiliateOfferInvitationFactory

`Features\Enrollment\Models\AffiliateOfferInvitation::factory()`

`pending()`, `accepted()`, `revoked()`, `expired()`, `forOffer($offer)`,
`forAffiliateId(string)`, `forEmail(string)`.

## AffiliateOfferDealFactory

`Features\Enrollment\Models\AffiliateOfferDeal::factory()`

`forOffer($offer)`, `forAffiliateId(string)`, `percentage(int $rateBps = 1500)`,
`flatRate(int $amountMinor = 500)`, `expired()`.

## AffiliateOfferCouponFactory

`Features\Attribution\Models\AffiliateOfferCoupon::factory()`

`forOffer($offer)`, `forAffiliateId(string)`, `inactive()`, `expired()`,
`withCode(string)`.

```php
$coupon = AffiliateOfferCoupon::factory()
    ->forOffer($offer)
    ->withCode('SUMMER10')
    ->create();
```

## AffiliateOfferLinkFactory

`Features\Attribution\Models\AffiliateOfferLink::factory()`

| State | Effect |
|---|---|
| `active()` / `inactive()` / `expired()` | Link state |
| `forOffer($offer)` / `forAffiliateId(string)` / `forSite($site)` | Relations |
| `withSlug(string)` / `withTarget(string)` | Backing link |
| `withSubIds(?sub1, ?sub2, ?sub3)` | Sub IDs |
| `withStats(int $clicks = 100, int $conversions = 10, int $revenue = 50000)` | Counters |
| `withCustomParams(string)` | Backing link params |
| `expiresAt(DateTimeInterface)` | Backing link expiry |

## NetworkConversionLegFactory

`Features\Ledger\Models\NetworkConversionLeg::factory()`

`forLink($link)`, `forOffer($offer)`, `pending()`, `provisional()`,
`superseded()`, `reversed()`, `forStatement(string $statementId)`.

```php
$leg = NetworkConversionLeg::factory()->forLink($link)->pending()->create([
    'revenue_minor' => 89900,
    'revenue_currency' => 'MYR',
    'external_reference' => 'ORDER-1001',
]);
```

Prefer `PostConversionLeg::execute()` when the test cares about commission,
tiers, fees, caps, or locking — the factory writes money fields verbatim.

## CreatorPostbackEndpointFactory

`Features\Postbacks\Models\CreatorPostbackEndpoint::factory()`

`forAffiliateId(string)`, `inactive()`, `withSecret(string $encryptedSecret)`,
`forEvents(array $events)`.

## CreatorPostbackDeliveryFactory

`Features\Postbacks\Models\CreatorPostbackDelivery::factory()`

`forEndpoint($endpoint)`, `delivered()`, `failed(string $error = 'Connection refused')`,
`pending()`.

## MerchantStatementFactory

`Features\Ledger\Models\MerchantStatement::factory()`

`forSite($site)`, `draft()`, `issued()`, `paid()`, `void()`, `forOwner($owner)`.

```php
$statement = MerchantStatement::factory()->forSite($site)->issued()->create();
```

## Architecture test

`tests/src/AffiliateNetwork/Arch/PackageArchTest.php` enforces:

- strict types across the package;
- no `dd`, `dump`, `ray`, `var_dump`, `sleep`, `usleep`, `time_nanosleep`, or `env` in source;
- no `App\*` imports;
- `Contracts`/`Data` stay free of Eloquent, Http, Features, and Drivers;
- features never import concrete drivers.

Run it with the rest of the package suite.

## Dune CLI verification

```bash
# Boundary + single-writer ownership (0 violations expected)
php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network

# Package-scoped PHPStan with the owned-write rule (0 errors expected)
./vendor/bin/phpstan analyse -c packages/affiliate-network/tools/package-phpstan.neon \
  --autoload-file=packages/affiliate-network/tools/Architecture/PackageOwnedWriteRule.php \
  --no-progress

# Scoped formatting and tests
./vendor/bin/pint packages/affiliate-network
./vendor/bin/pest --parallel tests/src/AffiliateNetwork
```

## Test examples

### Membership and approval

```php
use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;

it('auto-approves when the offer does not require approval', function (): void {
    $offer = AffiliateOffer::factory()->published()->autoApprove()->create();
    $affiliateId = (string) Str::uuid();

    $application = app(ApplyToOffer::class)->execute($offer->id, $affiliateId);

    expect($application->status)->toBe(ApplicationStatus::Approved);
});
```

### Conversions and balances

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\CreatorBalancesQuery;

it('posts a leg and derives creator balances', function (): void {
    $link = AffiliateOfferLink::factory()->create();

    app(RecordNetworkConversion::class)->execute(
        (string) $link->offer_id,
        (string) $link->affiliate_id,
        5999,
        'USD',
        'ORDER-TEST-1',
        linkId: (string) $link->getKey(),
    );

    $balances = app(CreatorBalancesQuery::class)->for((string) $link->affiliate_id);

    expect($balances)->toHaveKey('USD')
        ->and($balances['USD']->payableMinor)->toBeGreaterThan(0);
});
```

### Mocking drivers

```php
use AIArmada\AffiliateNetwork\Contracts\DnsResolver;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySiteByStrategy;

it('verifies through a mocked DNS resolver', function (): void {
    $dns = Mockery::mock(DnsResolver::class);
    $dns->shouldReceive('getTxtRecords')->once()->andReturn([['txt' => $site->verification_token]]);
    app()->instance(DnsResolver::class, $dns);

    $verified = app(VerifySiteByStrategy::class)->execute($site, 'dns');

    expect($verified->isVerified())->toBeTrue();
});
```

### Multi-tenancy

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

it('prevents cross-tenant offer creation', function (): void {
    $site = AffiliateSite::factory()->forOwner($merchantA)->create();

    OwnerContext::withOwner($merchantB, function () use ($site): void {
        expect(fn () => AffiliateOffer::create([
            'site_id' => $site->id,
            'name' => 'Cross-tenant',
        ]))->toThrow(RuntimeException::class);
    });
});
```
