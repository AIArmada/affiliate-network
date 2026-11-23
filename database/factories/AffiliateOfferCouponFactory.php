<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateOfferCoupon>
 */
class AffiliateOfferCouponFactory extends Factory
{
    protected $model = AffiliateOfferCoupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => AffiliateOfferFactory::new(),
            'site_id' => null,
            'affiliate_id' => fn () => (string) Str::uuid(),
            'code' => fn () => 'SAVE' . Str::upper(Str::random(8)),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AffiliateOfferCoupon $coupon): void {
            if ($coupon->site_id !== null) {
                return;
            }

            $offer = $coupon->offer;

            if ($offer instanceof AffiliateOffer) {
                $coupon->site_id = $offer->site_id;
            }
        });
    }

    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_id' => $offer->getKey(),
            'site_id' => $offer->site_id,
        ]);
    }

    public function forAffiliateId(string $affiliateId): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_id' => $affiliateId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => CarbonImmutable::now()->subDay(),
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }
}
