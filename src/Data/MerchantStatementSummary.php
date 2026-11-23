<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

use Carbon\CarbonImmutable;

final readonly class MerchantStatementSummary
{
    public function __construct(
        public string $id,
        public string $siteId,
        public string $statementNumber,
        public SettlementStatus $status,
        public string $currency,
        public int $legCount,
        public int $grossRevenueMinor,
        public int $commissionMinor,
        public int $feeMinor,
        public int $payoutMinor,
        public ?CarbonImmutable $issuedAt = null,
        public ?CarbonImmutable $dueAt = null,
        public ?CarbonImmutable $paidAt = null,
    ) {}
}
