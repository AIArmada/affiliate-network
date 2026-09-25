<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

/**
 * A network conversion ready to post, with network-owned math resolved.
 *
 * Commission and currency are computed by the network (which owns offer
 * rates); the ledger adapter only persists, guards, and accounts.
 */
final readonly class NetworkConversionDraft
{
    public function __construct(
        public string $linkId,
        public string $offerId,
        public ?string $siteId,
        public string $affiliateId,
        public string $linkCode,
        public int $revenueMinor,
        public string $currency,
        public string $externalReference,
        public int $commissionMinor,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
