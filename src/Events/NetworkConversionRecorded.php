<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class NetworkConversionRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public NetworkLegSnapshot $leg,
        public int $counterRevenueMinor,
        public ?string $currency = null,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}
}
