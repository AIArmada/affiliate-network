<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class FulfillmentResult
{
    public function __construct(
        public ?FulfillmentReceipt $receipt = null,
        public ?string $unfulfillableReason = null,
    ) {}
}
