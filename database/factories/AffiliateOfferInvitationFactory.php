<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateOfferInvitation>
 */
class AffiliateOfferInvitationFactory extends Factory
{
    protected $model = AffiliateOfferInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => AffiliateOfferFactory::new(),
            'affiliate_id' => null,
            'token' => fn () => 'inv-' . Str::ulid(),
            'invited_by' => null,
            'auto_approve' => true,
            'expires_at' => null,
            'accepted_at' => null,
            'revoked_at' => null,
            'metadata' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
            'revoked_at' => null,
            'expires_at' => CarbonImmutable::now()->addDays(14),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => CarbonImmutable::now(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => CarbonImmutable::now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => CarbonImmutable::now()->subDay(),
        ]);
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

    public function forEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge(
                is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [],
                ['invited_email' => $email],
            ),
        ]);
    }
}
