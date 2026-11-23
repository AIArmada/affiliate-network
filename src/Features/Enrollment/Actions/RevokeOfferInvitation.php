<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use Carbon\CarbonImmutable;

final class RevokeOfferInvitation
{
    public function execute(AffiliateOfferInvitation $invitation): AffiliateOfferInvitation
    {
        if ($invitation->revoked_at === null) {
            $invitation->update(['revoked_at' => CarbonImmutable::now()]);
        }

        return $invitation->refresh();
    }
}
