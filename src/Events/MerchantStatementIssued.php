<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use AIArmada\AffiliateNetwork\Data\MerchantStatementSummary;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class MerchantStatementIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public MerchantStatementSummary $statement,
    ) {}
}
