<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Api;

final readonly class OfferLinkRecord
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public ?string $linkId,
        public string $offerId,
        public string $affiliateId,
        public ?string $siteId,
        public ?string $slug,
        public ?string $subId,
        public ?string $subId2,
        public ?string $subId3,
        public int $clicks,
        public int $conversions,
        public int $revenue,
        public ?string $currency,
        public bool $isActive,
        public bool $isExpired,
        public ?array $metadata,
    ) {}
}
