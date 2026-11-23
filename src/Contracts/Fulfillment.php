<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\FulfillmentResult;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;

interface Fulfillment
{
    public function fulfillsAutomatically(): bool;

    public function fulfill(NetworkLegSnapshot $leg, ?string $reference = null): FulfillmentResult;
}
