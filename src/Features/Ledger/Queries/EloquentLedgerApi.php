<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Queries;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\CheckOfferCaps;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use Carbon\CarbonImmutable;

final class EloquentLedgerApi implements LedgerApi
{
    public function __construct(
        private readonly CheckOfferCaps $caps,
    ) {}

    public function isOfferCapped(string $offerId, ?string $affiliateId = null, int $proposedPayoutMinor = 0): ?string
    {
        return $this->caps->checkReason($offerId, $affiliateId, $proposedPayoutMinor);
    }

    public function findLegByIdempotencyKeyUnscoped(string $idempotencyKey): ?NetworkLegSnapshot
    {
        return NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('idempotency_key', $idempotencyKey)
            ->first()
            ?->toSnapshot();
    }

    public function findLegByLinkOrCouponUnscoped(
        ?string $linkId,
        ?string $couponCode,
        string $externalReference,
        string $eventType = 'purchase',
    ): ?NetworkLegSnapshot {
        $query = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('external_reference', $externalReference)
            ->where('event_type', $eventType);

        if ($linkId !== null && $linkId !== '') {
            $query->where('link_id', $linkId);
        } elseif ($couponCode !== null && $couponCode !== '') {
            $query->where('coupon_code', mb_strtoupper(mb_trim($couponCode)));
        } else {
            return null;
        }

        return $query->first()?->toSnapshot();
    }

    public function offerConversionMetrics(array $offerIds, CarbonImmutable $since): array
    {
        if ($offerIds === []) {
            return [];
        }

        $metrics = [];

        foreach ($offerIds as $offerId) {
            $metrics[(string) $offerId] = ['conversions' => 0, 'reversed' => 0, 'revenue_minor' => 0, 'payout_minor' => 0];
        }

        $rows = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereIn('offer_id', array_keys($metrics))
            ->where('occurred_at', '>=', $since)
            ->whereIn('status', [LegStatus::Pending, LegStatus::Posted, LegStatus::Reversed])
            ->whereNull('parent_leg_id')
            ->selectRaw('offer_id, status, COUNT(*) as conversions, SUM(revenue_minor) as revenue_minor, SUM(payout_minor) as payout_minor')
            ->groupBy('offer_id', 'status')
            ->get();

        foreach ($rows as $row) {
            /** @var array{offer_id: string, status: string, conversions: int|string, revenue_minor: int|string, payout_minor: int|string} $row */
            $offerId = (string) $row['offer_id'];
            $metrics[$offerId] ??= ['conversions' => 0, 'reversed' => 0, 'revenue_minor' => 0, 'payout_minor' => 0];

            $count = (int) $row['conversions'];

            if ($row['status'] === LegStatus::Reversed || $row['status'] === LegStatus::Reversed->value) {
                $metrics[$offerId]['reversed'] += $count;

                continue;
            }

            $metrics[$offerId]['conversions'] += $count;
            $metrics[$offerId]['revenue_minor'] += (int) $row['revenue_minor'];
            $metrics[$offerId]['payout_minor'] += (int) $row['payout_minor'];
        }

        return $metrics;
    }
}
