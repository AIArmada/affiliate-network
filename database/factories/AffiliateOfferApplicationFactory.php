<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateOfferApplication>
 */
class AffiliateOfferApplicationFactory extends Factory
{
    protected $model = AffiliateOfferApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => AffiliateOfferFactory::new(),
            'affiliate_id' => fn () => Affiliate::create([
                'code' => 'AFF' . $this->faker->unique()->numberBetween(1000, 9999),
                'name' => $this->faker->name(),
                'status' => 'active',
                'commission_type' => 'percentage',
                'commission_rate' => 1000,
                'currency' => 'USD',
            ])->id,
            'status' => ApplicationStatus::Pending,
            'reason' => $this->faker->optional()->sentence(),
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Application in pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    /**
     * Application that is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Approved,
            'reviewed_by' => 'admin',
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Application that is rejected.
     */
    public function rejected(string $reason = 'Does not meet requirements'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => 'admin',
            'reviewed_at' => now(),
            'rejected_at' => now(),
        ]);
    }

    /**
     * Application that is revoked.
     */
    public function revoked(string $reason = 'Terms of service violation'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Revoked,
            'rejection_reason' => $reason,
            'reviewed_by' => 'admin',
            'reviewed_at' => now(),
            'revoked_at' => now(),
        ]);
    }

    /**
     * Application for a specific offer.
     */
    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_id' => $offer->id,
        ]);
    }

    /**
     * Application by a specific affiliate.
     */
    public function forAffiliate(Affiliate $affiliate): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_id' => $affiliate->id,
        ]);
    }

    /**
     * Application with a reason.
     */
    public function withReason(string $reason): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => $reason,
        ]);
    }
}
