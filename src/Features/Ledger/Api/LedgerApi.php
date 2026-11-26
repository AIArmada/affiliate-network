<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Api;

use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use Carbon\CarbonImmutable;

interface LedgerApi
{
    public function isOfferCapped(string $offerId, ?string $affiliateId = null, int $proposedPayoutMinor = 0): ?string;

    public function findLegByIdempotencyKeyUnscoped(string $idempotencyKey): ?NetworkLegSnapshot;

    public function findLegByLinkOrCouponUnscoped(
        ?string $linkId,
        ?string $couponCode,
        string $externalReference,
        string $eventType = 'purchase',
    ): ?NetworkLegSnapshot;

    /**
     * @param  array<int, string>  $offerIds
     * @return array<string, array{conversions: int, reversed: int, revenue_minor: int, payout_minor: int}>
     */
    public function offerConversionMetrics(array $offerIds, CarbonImmutable $since): array;

    public function countPendingLegsUnscoped(): int;

    public function countIssuedStatementsUnscoped(): int;
}
