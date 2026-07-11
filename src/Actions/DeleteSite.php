<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteSite
{
    public function execute(AffiliateSite $site): void
    {
        DB::transaction(function () use ($site): void {
            $offers = AffiliateOffer::withoutGlobalScopes()
                ->where('site_id', $site->id)
                ->get();

            $offerIds = $offers->pluck('id');

            AffiliateOfferCreative::withoutGlobalScopes()
                ->whereIn('offer_id', $offerIds)
                ->delete();

            AffiliateOfferApplication::withoutGlobalScopes()
                ->whereIn('offer_id', $offerIds)
                ->delete();

            AffiliateOfferLink::withoutGlobalScopes()
                ->whereIn('offer_id', $offerIds)
                ->delete();

            AffiliateOffer::withoutGlobalScopes()
                ->whereIn('id', $offerIds)
                ->delete();

            $site->delete();

            $this->postDeleteVerify($site, $offerIds);
        });
    }

    protected function postDeleteVerify(AffiliateSite $site, iterable $offerIds): void
    {
        if (AffiliateSite::find($site->id) !== null) {
            throw new RuntimeException('Site was not fully deleted.');
        }

        $remainingOffers = AffiliateOffer::withoutGlobalScopes()
            ->whereIn('id', $offerIds)
            ->count();

        if ($remainingOffers > 0) {
            throw new RuntimeException('Not all offers were deleted.');
        }
    }
}
