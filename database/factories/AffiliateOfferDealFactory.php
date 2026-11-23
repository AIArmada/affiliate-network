<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateOfferDeal>
 */
class AffiliateOfferDealFactory extends Factory
{
    protected $model = AffiliateOfferDeal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => AffiliateOfferFactory::new(),
            'affiliate_id' => fn () => (string) Str::uuid(),
            'rate_base_bp' => 1500,
            'rate_fixed_minor' => null,
            'network_fee_bp' => null,
            'currency' => 'MYR',
            'volume_tiers' => null,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_id' => $offer->getKey(),
        ]);
    }

    public function forAffiliateId(string $affiliateId): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_id' => $affiliateId,
        ]);
    }

    public function percentage(int $rateBps = 1500): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_base_bp' => $rateBps,
            'rate_fixed_minor' => null,
        ]);
    }

    public function flatRate(int $amountMinor = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_base_bp' => null,
            'rate_fixed_minor' => $amountMinor,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => CarbonImmutable::now()->subDay(),
        ]);
    }
}
