<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantStatement>
 */
class MerchantStatementFactory extends Factory
{
    protected $model = MerchantStatement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodEnd = CarbonImmutable::now()->startOfMonth();

        return [
            'owner_type' => null,
            'owner_id' => null,
            'site_id' => AffiliateSiteFactory::new()->verified(),
            'statement_number' => fn () => 'MS-' . Str::upper(Str::random(10)),
            'status' => SettlementStatus::Draft,
            'currency' => 'MYR',
            'period_start' => $periodEnd->subMonth(),
            'period_end' => $periodEnd,
            'leg_count' => 0,
            'gross_revenue_minor' => 0,
            'commission_minor' => 0,
            'fee_minor' => 0,
            'payout_minor' => 0,
            'issued_at' => null,
            'due_at' => null,
            'paid_at' => null,
            'payment_reference' => null,
            'metadata' => null,
        ];
    }

    public function forSite(AffiliateSite $site): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->getKey(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::Draft,
            'issued_at' => null,
            'paid_at' => null,
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::Issued,
            'issued_at' => CarbonImmutable::now(),
            'due_at' => CarbonImmutable::now()->addDays(30),
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::Paid,
            'issued_at' => CarbonImmutable::now()->subDays(10),
            'due_at' => CarbonImmutable::now()->addDays(20),
            'paid_at' => CarbonImmutable::now(),
            'payment_reference' => 'PAY-' . Str::upper(Str::random(8)),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::Void,
        ]);
    }

    public function forOwner(object $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
    }
}
