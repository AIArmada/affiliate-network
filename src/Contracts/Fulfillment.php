<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;

/**
 * Creator-leg fulfillment seam.
 *
 * The network records obligations (legs); exactly one adapter per
 * install fulfills them. Merchant payouts run automatically when the
 * engine is bound, while standalone hosts explicitly record payout runs.
 */
interface Fulfillment
{
    public function fulfillsAutomatically(): bool;

    public function fulfill(NetworkConversionLeg $leg, ?string $reference = null): ?FulfillmentReceipt;
}
