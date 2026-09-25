<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NetworkConversionLeg>
 */
class NetworkConversionLegFactory extends Factory
{
    protected $model = NetworkConversionLeg::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => AffiliateOfferLinkFactory::new(),
            'offer_id' => AffiliateOfferFactory::new(),
            'site_id' => null,
            'affiliate_id' => fn () => (string) Str::uuid(),
            'link_code' => fn () => Str::random(16),
            'revenue_minor' => 89900,
            'revenue_currency' => 'MYR',
            'commission_minor' => 13485,
            'commission_currency' => 'MYR',
            'fee_minor' => 0,
            'fee_bp' => 0,
            'payout_minor' => 13485,
            'tier_rate_bp' => null,
            'tier_min_volume_minor' => null,
            'external_reference' => fn () => 'ORDER-' . Str::upper(Str::random(8)),
            'status' => LegStatus::Posted,
            'metadata' => null,
            'occurred_at' => CarbonImmutable::now(),
        ];
    }

    public function forLink(AffiliateOfferLink $link): static
    {
        return $this->state(fn (): array => [
            'link_id' => $link->getKey(),
            'offer_id' => $link->offer_id,
            'site_id' => $link->site_id,
            'affiliate_id' => (string) $link->affiliate_id,
        ]);
    }

    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (): array => [
            'offer_id' => $offer->getKey(),
            'site_id' => $offer->site_id,
        ]);
    }

    public function provisional(): static
    {
        return $this->state(fn (): array => ['status' => LegStatus::Provisional]);
    }

    public function superseded(): static
    {
        return $this->state(fn (): array => ['status' => LegStatus::Superseded]);
    }
}
