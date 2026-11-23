<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ConversionReversed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public NetworkLegSnapshot $originalLeg,
        public NetworkLegSnapshot $reversalLeg,
        public string $reason,
    ) {}
}
