<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ConversionAdjusted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public NetworkLegSnapshot $originalLeg,
        public NetworkLegSnapshot $adjustmentLeg,
        public string $reason,
    ) {}
}
