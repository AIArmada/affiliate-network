<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use RuntimeException;

final class DeleteCreative
{
    public function execute(AffiliateOfferCreative $creative): bool
    {
        $offer = AffiliateOffer::query()->whereKey($creative->offer_id)->firstOrFail();
        $creative = $offer->creatives()->whereKey($creative->getKey())->firstOrFail();

        if ($creative->external_creative_id !== null) {
            throw new RuntimeException('Imported creatives are managed by catalog sync.');
        }

        return (bool) $creative->delete();
    }
}
