<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;

final class DeleteOfferLinks
{
    public function execute(string $offerId): void
    {
        AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        AffiliateOfferCoupon::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();
    }
}
