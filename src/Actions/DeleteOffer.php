<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteOffer
{
    public function execute(AffiliateOffer $offer): void
    {
        DB::transaction(function () use ($offer): void {
            $offerId = [$offer->id];

            AffiliateOfferCreative::withoutGlobalScopes()
                ->whereIn('offer_id', $offerId)
                ->delete();

            AffiliateOfferApplication::withoutGlobalScopes()
                ->whereIn('offer_id', $offerId)
                ->delete();

            AffiliateOfferLink::withoutGlobalScopes()
                ->whereIn('offer_id', $offerId)
                ->delete();

            $offer->delete();

            $this->postDeleteVerify($offer);
        });
    }

    protected function postDeleteVerify(AffiliateOffer $offer): void
    {
        if (AffiliateOffer::find($offer->id) !== null) {
            throw new RuntimeException('Offer was not fully deleted.');
        }
    }
}
