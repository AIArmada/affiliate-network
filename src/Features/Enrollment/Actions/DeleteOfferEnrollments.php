<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;

final class DeleteOfferEnrollments
{
    public function execute(string $offerId): void
    {
        AffiliateOfferApplication::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        AffiliateOfferInvitation::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        AffiliateOfferDeal::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();
    }
}
