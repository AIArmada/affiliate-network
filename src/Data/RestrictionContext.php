<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class RestrictionContext
{
    public function __construct(
        public ?string $country = null,
        public ?string $channel = null,
        public ?string $device = null,
        public ?int $orderMinor = null,
        public ?string $currency = null,
    ) {}
}
