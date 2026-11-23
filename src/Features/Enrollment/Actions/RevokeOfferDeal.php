<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;

final class RevokeOfferDeal
{
    public function execute(AffiliateOfferDeal $deal): AffiliateOfferDeal
    {
        if ((bool) $deal->is_active) {
            $deal->update(['is_active' => false]);
        }

        return $deal->refresh();
    }
}
