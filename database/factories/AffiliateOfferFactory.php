<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateOffer>
 */
class AffiliateOfferFactory extends Factory
{
    protected $model = AffiliateOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'site_id' => AffiliateSiteFactory::new()->verified(),
            'category_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraph(),
            'terms' => $this->faker->paragraph(),
            'status' => AffiliateOffer::STATUS_ACTIVE,
            'commission_type' => 'percentage',
            'commission_rate' => $this->faker->numberBetween(500, 2500),
            'currency' => 'USD',
            'cookie_days' => 30,
            'is_featured' => false,
            'is_public' => true,
            'requires_approval' => true,
            'landing_url' => $this->faker->url(),
            'restrictions' => null,
            'metadata' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Offer in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateOffer::STATUS_DRAFT,
        ]);
    }

    /**
     * Offer in pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateOffer::STATUS_PENDING,
        ]);
    }

    /**
     * Offer in active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateOffer::STATUS_ACTIVE,
        ]);
    }

    /**
     * Offer in paused status.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateOffer::STATUS_PAUSED,
        ]);
    }

    /**
     * Offer in expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateOffer::STATUS_EXPIRED,
            'ends_at' => now()->subDays(1),
        ]);
    }

    /**
     * Featured offer.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Private offer (not public).
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Offer that doesn't require approval.
     */
    public function autoApprove(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => false,
        ]);
    }

    /**
     * Offer with a specific site.
     */
    public function forSite(AffiliateSite $site): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->id,
        ]);
    }

    /**
     * Offer with a specific category.
     */
    public function forCategory(AffiliateOfferCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    /**
     * Offer with flat-rate commission.
     */
    public function flatRate(int $amountMinor = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_type' => 'flat',
            'commission_rate' => $amountMinor,
        ]);
    }

    /**
     * Offer with percentage commission.
     */
    public function percentage(int $rateBps = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_type' => 'percentage',
            'commission_rate' => $rateBps,
        ]);
    }

    /**
     * Offer with time range.
     */
    public function withDateRange(DateTimeInterface $startsAt, DateTimeInterface $endsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
