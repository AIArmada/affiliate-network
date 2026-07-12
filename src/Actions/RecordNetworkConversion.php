<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;

final class RecordNetworkConversion
{
    public function execute(AffiliateOfferLink $link, int $revenueMinor = 0): void
    {
        $link->recordConversion($revenueMinor);

        event(new NetworkConversionRecorded($link, $revenueMinor));
    }
}
