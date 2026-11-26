<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Api;

use Carbon\CarbonImmutable;

final readonly class RecentApplication
{
    public function __construct(
        public string $id,
        public ?string $offerName,
        public ?string $affiliateCode,
        public CarbonImmutable $createdAt,
    ) {}
}
