<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Contracts\AuthorizesApplicationAdmin;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Illuminate\Database\Eloquent\Model;

final class DefaultApplicationAdminAuthorizer implements AuthorizesApplicationAdmin
{
    public function canAdministerApplications(?Model $user, AffiliateOfferApplication $application): bool
    {
        if ($user === null) {
            return false;
        }

        $application->loadMissing('offer.site');

        $site = $application->offer?->site;

        if ($site === null) {
            return false;
        }

        if ($site->owner_type === $user->getMorphClass() && (string) $site->owner_id === (string) $user->getKey()) {
            return true;
        }

        $adminCallback = config('affiliate-network.admin.authorization_callback');

        if ($adminCallback !== null && $adminCallback($user, $application)) {
            return true;
        }

        return false;
    }
}
