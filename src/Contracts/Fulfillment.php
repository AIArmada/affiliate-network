<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;

/**
 * Creator-leg fulfillment seam.
 *
 * The network records obligations (legs); exactly one adapter per
 * install fulfills them — merchant payouts when the engine is bound,
 * host-recorded runs otherwise. Mutual exclusion is enforced by the
 * service provider: two payers can never both be active.
 */
interface Fulfillment
{
    public function fulfill(NetworkConversionLeg $leg, ?string $reference = null): FulfillmentReceipt;
}
