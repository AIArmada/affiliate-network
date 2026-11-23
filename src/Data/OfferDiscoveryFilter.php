<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class OfferDiscoveryFilter
{
    public function __construct(
        public ?string $categoryId = null,
        public ?string $search = null,
        public ?string $currency = null,
        public ?int $minRateBp = null,
        public ?bool $requiresApproval = null,
        public ?bool $featured = null,
        public ?string $country = null,
        public ?string $channel = null,
        public ?string $device = null,
        public string $sort = 'featured',
        public int $perPage = 20,
    ) {}
}
