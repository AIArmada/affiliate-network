<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateOfferLink>
 */
class AffiliateOfferLinkFactory extends Factory
{
    protected $model = AffiliateOfferLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => AffiliateOfferFactory::new(),
            'affiliate_id' => fn () => (string) Str::uuid(),
            'site_id' => null,
            'code' => bin2hex(random_bytes(8)),
            'target_url' => $this->faker->url(),
            'custom_parameters' => null,
            'sub_id' => null,
            'sub_id_2' => null,
            'sub_id_3' => null,
            'clicks' => 0,
            'conversions' => 0,
            'revenue' => 0,
            'currency' => 'MYR',
            'is_active' => true,
            'expires_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Link that is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Link that is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Link that is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Link for a specific offer.
     */
    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_id' => $offer->id,
            'site_id' => $offer->site_id,
            'currency' => $offer->currency,
        ]);
    }

    /**
     * Link for a specific affiliate.
     */
    public function forAffiliateId(string $affiliateId): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_id' => $affiliateId,
        ]);
    }

    /**
     * Link for a specific site.
     */
    public function forSite(AffiliateSite $site): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->id,
        ]);
    }

    /**
     * Link with sub IDs.
     */
    public function withSubIds(?string $sub1 = null, ?string $sub2 = null, ?string $sub3 = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sub_id' => $sub1 ?? $this->faker->word(),
            'sub_id_2' => $sub2 ?? $this->faker->word(),
            'sub_id_3' => $sub3 ?? $this->faker->word(),
        ]);
    }

    /**
     * Link with stats.
     *
     * Counters are deliberately not fillable, so they are stamped after
     * creation instead of going through mass assignment.
     */
    public function withStats(int $clicks = 100, int $conversions = 10, int $revenue = 50000): static
    {
        return $this->afterCreating(function (AffiliateOfferLink $link) use ($clicks, $conversions, $revenue): void {
            $link->forceFill([
                'clicks' => $clicks,
                'conversions' => $conversions,
                'revenue' => $revenue,
            ])->save();
        });
    }

    /**
     * Link with custom parameters.
     */
    public function withCustomParams(string $params): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_parameters' => $params,
        ]);
    }

    /**
     * Link with expiration.
     */
    public function expiresAt(DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $date,
        ]);
    }
}
