<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class OfferPerformanceMetrics
{
    public function __construct(
        public string $offerId,
        public int $clicks,
        public int $conversions,
        public int $reversedConversions,
        public int $revenueMinor,
        public int $payoutMinor,
        public float $conversionRate,
        public float $reversalRate,
        public float $epcMinor,
        public ?float $avgApprovalHours,
        public string $currency,
    ) {}
}
