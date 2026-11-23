<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Queries;

use AIArmada\AffiliateNetwork\Data\OfferPerformanceMetrics;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use Carbon\CarbonImmutable;

final class OfferMetricsQuery
{
    public function __construct(
        private readonly AttributionApi $attribution,
        private readonly LedgerApi $ledger,
        private readonly EnrollmentApi $enrollments,
    ) {}

    public function forOffer(OfferRecord $offer, int $windowDays = 30): OfferPerformanceMetrics
    {
        return $this->batch([$offer], $windowDays)[$offer->id];
    }

    /**
     * @param  list<OfferRecord>  $offers
     * @return array<string, OfferPerformanceMetrics>
     */
    public function batch(array $offers, int $windowDays = 30): array
    {
        if ($offers === []) {
            return [];
        }

        $windowDays = max(1, $windowDays);
        $since = CarbonImmutable::now()->subDays($windowDays);
        $offerIds = array_map(static fn (OfferRecord $offer): string => $offer->id, $offers);

        $linkTotals = $this->attribution->offerLinkTotals($offerIds);
        $legMetrics = $this->ledger->offerConversionMetrics($offerIds, $since);

        $metrics = [];

        foreach ($offers as $offer) {
            $totals = $linkTotals[$offer->id] ?? ['clicks' => 0, 'conversions' => 0, 'revenue_minor' => 0];
            $legs = $legMetrics[$offer->id] ?? ['conversions' => 0, 'reversed' => 0, 'revenue_minor' => 0, 'payout_minor' => 0];

            $clicks = $totals['clicks'];
            $conversions = $legs['conversions'];
            $reversed = $legs['reversed'];

            $metrics[$offer->id] = new OfferPerformanceMetrics(
                offerId: $offer->id,
                clicks: $clicks,
                conversions: $conversions,
                reversedConversions: $reversed,
                revenueMinor: max(0, $totals['revenue_minor']),
                payoutMinor: $legs['payout_minor'],
                conversionRate: $clicks > 0 ? round($conversions / $clicks, 4) : 0.0,
                reversalRate: $conversions > 0 ? round($reversed / $conversions, 4) : 0.0,
                epcMinor: $clicks > 0 ? round($legs['payout_minor'] / $clicks, 2) : 0.0,
                avgApprovalHours: $this->enrollments->averageApprovalHoursForOffer($offer->id, $since),
                currency: mb_strtoupper((string) ($offer->currency ?? config('affiliate-network.currency.default', 'MYR'))),
            );
        }

        return $metrics;
    }
}
