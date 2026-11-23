<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class CreatorBalanceSummary
{
    public function __construct(
        public string $currency,
        public int $pendingMinor,
        public int $payableMinor,
        public int $paidMinor,
        public int $reversedMinor,
        public int $netPayableMinor,
    ) {}
}
