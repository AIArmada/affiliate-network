<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Api;

use Carbon\CarbonImmutable;

final readonly class DealRateOverride
{
    /**
     * @param  array<int, array<string, mixed>>|null  $volumeTiers
     */
    public function __construct(
        public ?int $rateBaseBp,
        public ?int $rateFixedMinor,
        public ?int $networkFeeBp,
        public ?string $currency,
        public ?array $volumeTiers,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public bool $isActive,
    ) {}
}
