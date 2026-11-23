<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
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
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'description' => $this->faker->paragraph(),
            'terms' => $this->faker->paragraph(),
            'status' => OfferStatus::Published,
            'source' => OfferSource::Manual,
            'rate_base_bp' => $this->faker->numberBetween(500, 2500),
            'rate_fixed_minor' => null,
            'network_fee_bp' => null,
            'currency' => 'MYR',
            'cookie_days' => 30,
            'locking_days' => 0,
            'volume_tiers' => null,
            'active_promotions' => null,
            'event_rates' => null,
            'is_featured' => false,
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
            'landing_url' => null,
            'fallback_url' => null,
            'fallback_offer_id' => null,
            'allow_deep_linking' => true,
            'restrictions' => null,
            'metadata' => null,
            'daily_conversion_cap' => null,
            'monthly_conversion_cap' => null,
            'total_conversion_cap' => null,
            'per_affiliate_conversion_cap' => null,
            'monthly_payout_cap_minor' => null,
            'total_payout_cap_minor' => null,
            'starts_at' => null,
            'ends_at' => null,
            'published_at' => now()->subDay(),
            'archived_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AffiliateOffer $offer): void {
            if ($offer->landing_url !== null) {
                return;
            }

            $domain = $offer->site?->domain;

            if (is_string($domain) && $domain !== '') {
                $offer->landing_url = 'https://' . $domain . '/offers/' . $offer->slug;
            }
        });
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Published,
            'published_at' => now()->subDay(),
            'archived_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => OfferVisibility::Private,
        ]);
    }

    public function unlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => OfferVisibility::Unlisted,
        ]);
    }

    public function autoApprove(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => false,
        ]);
    }

    public function forSite(AffiliateSite $site): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->id,
        ]);
    }

    public function forCategory(AffiliateOfferCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function flatRate(int $amountMinor = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_base_bp' => null,
            'rate_fixed_minor' => $amountMinor,
        ]);
    }

    public function percentage(int $rateBps = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_base_bp' => $rateBps,
            'rate_fixed_minor' => null,
        ]);
    }

    public function withDateRange(DateTimeInterface $startsAt, DateTimeInterface $endsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function withLockingDays(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'locking_days' => $days,
        ]);
    }

    public function withCaps(
        ?int $daily = null,
        ?int $monthly = null,
        ?int $total = null,
        ?int $perAffiliate = null,
        ?int $monthlyPayoutMinor = null,
        ?int $totalPayoutMinor = null,
    ): static {
        return $this->state(fn (array $attributes) => [
            'daily_conversion_cap' => $daily,
            'monthly_conversion_cap' => $monthly,
            'total_conversion_cap' => $total,
            'per_affiliate_conversion_cap' => $perAffiliate,
            'monthly_payout_cap_minor' => $monthlyPayoutMinor,
            'total_payout_cap_minor' => $totalPayoutMinor,
        ]);
    }

    public function withFallbackUrl(string $fallbackUrl = 'https://example.com/fallback'): static
    {
        return $this->state(fn (array $attributes) => [
            'fallback_url' => $fallbackUrl,
        ]);
    }

    public function withFallbackOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (array $attributes) => [
            'fallback_offer_id' => $offer->id,
        ]);
    }

    /**
     * @param  array<string, int>  $rates
     */
    public function withEventRates(array $rates): static
    {
        return $this->state(fn (array $attributes) => [
            'event_rates' => $rates,
        ]);
    }

    /**
     * @param  array<string, mixed>  $restrictions
     */
    public function withRestrictions(array $restrictions): static
    {
        return $this->state(fn (array $attributes) => [
            'restrictions' => $restrictions,
        ]);
    }

    /**
     * @param  array<int, mixed>  $promotions
     */
    public function withPromotions(array $promotions): static
    {
        return $this->state(fn (array $attributes) => [
            'active_promotions' => $promotions,
        ]);
    }
}
