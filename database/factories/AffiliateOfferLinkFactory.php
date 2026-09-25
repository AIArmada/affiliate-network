<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Support\QueryParameters;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Models\Link;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RuntimeException;

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
            'link_id' => null,
            'sub_id' => null,
            'sub_id_2' => null,
            'sub_id_3' => null,
            'clicks' => 0,
            'conversions' => 0,
            'revenue' => 0,
            'currency' => 'MYR',
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        // Every offer link rides on a signed tracked link, mirroring the
        // service: slug minted by links, attribution parameter pointing at it.
        return $this->afterCreating(function (AffiliateOfferLink $link): void {
            $tracked = CreateLink::run([
                'name' => sprintf('Factory offer link %s', mb_substr((string) $link->getKey(), 0, 8)),
                'destination_url' => $this->faker->url(),
                'require_signature' => true,
                'subject_type' => $link->getMorphClass(),
                'subject_id' => (string) $link->getKey(),
            ], false);

            $tracked = UpdateLink::run($tracked, [
                'parameters' => [
                    (string) config('affiliate-network.links.parameter', 'anl') => $tracked->slug,
                ],
            ], false);

            $link->forceFill(['link_id' => $tracked->id])->save();
            $link->setRelation('link', $tracked);
        });
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
        return $this->afterCreating(function (AffiliateOfferLink $link): void {
            $tracked = UpdateLink::run($this->trackedLink($link), [
                'expires_at' => now()->subDays(1)->toDateTimeString(),
            ], false);

            $link->setRelation('link', $tracked);
        });
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
     * Link with a specific tracked slug.
     */
    public function withSlug(string $slug): static
    {
        return $this->afterCreating(function (AffiliateOfferLink $link) use ($slug): void {
            $tracked = UpdateLink::run($this->trackedLink($link), ['slug' => $slug], false);

            $tracked = UpdateLink::run($tracked, [
                'parameters' => array_merge(
                    is_array($tracked->parameters) ? $tracked->parameters : [],
                    [(string) config('affiliate-network.links.parameter', 'anl') => $tracked->slug],
                ),
            ], false);

            $link->setRelation('link', $tracked);
        });
    }

    /**
     * Link with a specific destination URL.
     */
    public function withTarget(string $targetUrl): static
    {
        return $this->afterCreating(function (AffiliateOfferLink $link) use ($targetUrl): void {
            $tracked = UpdateLink::run($this->trackedLink($link), ['destination_url' => $targetUrl], false);

            $link->setRelation('link', $tracked);
        });
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
        ])->afterCreating(function (AffiliateOfferLink $link): void {
            $tracked = $this->trackedLink($link);

            $tracked = UpdateLink::run($tracked, [
                'parameters' => array_merge(
                    is_array($tracked->parameters) ? $tracked->parameters : [],
                    array_filter([
                        'sub1' => $link->sub_id,
                        'sub2' => $link->sub_id_2,
                        'sub3' => $link->sub_id_3,
                    ]),
                ),
            ], false);

            $link->setRelation('link', $tracked);
        });
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
        return $this->afterCreating(function (AffiliateOfferLink $link) use ($params): void {
            $custom = QueryParameters::parse($params);

            $tracked = $this->trackedLink($link);

            // Core attribution parameters win over custom ones.
            $tracked = UpdateLink::run($tracked, [
                'parameters' => array_merge(
                    $custom,
                    is_array($tracked->parameters) ? $tracked->parameters : [],
                ),
            ], false);

            $link->setRelation('link', $tracked);
        });
    }

    /**
     * Link with expiration.
     */
    public function expiresAt(DateTimeInterface $date): static
    {
        return $this->afterCreating(function (AffiliateOfferLink $link) use ($date): void {
            $tracked = UpdateLink::run($this->trackedLink($link), [
                'expires_at' => $date,
            ], false);

            $link->setRelation('link', $tracked);
        });
    }

    private function trackedLink(AffiliateOfferLink $link): Link
    {
        $tracked = $link->link()->withoutOwnerScope()->first();

        if (! $tracked instanceof Link) {
            throw new RuntimeException('Factory offer link has no tracked link.');
        }

        return $tracked;
    }
}
