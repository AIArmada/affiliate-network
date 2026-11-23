<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Api;

use Carbon\CarbonImmutable;

final readonly class OfferCouponRecord
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $offerId,
        public string $siteId,
        public string $affiliateId,
        public string $code,
        public bool $isActive,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?array $metadata,
    ) {}
}
