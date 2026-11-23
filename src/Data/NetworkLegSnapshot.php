<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

use Carbon\CarbonImmutable;

final readonly class NetworkLegSnapshot
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public ?string $linkId,
        public string $offerId,
        public ?string $siteId,
        public string $affiliateId,
        public ?string $linkCode,
        public int $revenueMinor,
        public ?string $revenueCurrency,
        public int $commissionMinor,
        public string $commissionCurrency,
        public int $feeMinor,
        public int $feeBp,
        public int $payoutMinor,
        public ?int $tierRateBp,
        public ?int $tierMinVolumeMinor,
        public string $externalReference,
        public string $eventType,
        public LegStatus $status,
        public ?CarbonImmutable $locksAt,
        public ?CarbonImmutable $lockedAt,
        public ?CarbonImmutable $fulfilledAt,
        public ?string $statementId,
        public ?string $couponCode,
        public ?string $subId,
        public ?string $subId2,
        public ?string $subId3,
        public array $metadata = [],
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
