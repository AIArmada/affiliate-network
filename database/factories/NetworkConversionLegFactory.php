<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
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
            'link_id' => null,
            'offer_id' => AffiliateOfferFactory::new(),
            'site_id' => null,
            'affiliate_id' => fn () => (string) Str::uuid(),
            'link_code' => fn () => Str::random(16),
            'idempotency_key' => fn () => 'leg-' . Str::uuid(),
            'parent_leg_id' => null,
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
            'event_type' => 'sale',
            'status' => LegStatus::Posted,
            'coupon_code' => null,
            'sub_id' => null,
            'sub_id_2' => null,
            'sub_id_3' => null,
            'statement_id' => null,
            'metadata' => null,
            'locks_at' => null,
            'locked_at' => null,
            'fulfilled_at' => null,
            'occurred_at' => CarbonImmutable::now(),
        ];
    }

    public function forLink(AffiliateOfferLink $link): static
    {
        return $this->state(fn (): array => [
            'link_id' => $link->link_id,
            'offer_id' => $link->offer_id,
            'site_id' => $link->site_id,
            'affiliate_id' => (string) $link->affiliate_id,
            'sub_id' => $link->sub_id,
            'sub_id_2' => $link->sub_id_2,
            'sub_id_3' => $link->sub_id_3,
        ]);
    }

    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (): array => [
            'offer_id' => $offer->getKey(),
            'site_id' => $offer->site_id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => LegStatus::Pending]);
    }

    public function provisional(): static
    {
        return $this->state(fn (): array => ['status' => LegStatus::Provisional]);
    }

    public function superseded(): static
    {
        return $this->state(fn (): array => ['status' => LegStatus::Superseded]);
    }

    public function reversed(): static
    {
        return $this->state(fn (): array => ['status' => LegStatus::Reversed]);
    }

    public function forStatement(string $statementId): static
    {
        return $this->state(fn (): array => ['statement_id' => $statementId]);
    }
}
