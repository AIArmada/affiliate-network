<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;

final class UpdateOffer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOffer $offer, array $data): AffiliateOffer
    {
        $offer->update($data);

        $fresh = $offer->fresh();

        event(new OfferUpdated($fresh));

        return $fresh;
    }
}
