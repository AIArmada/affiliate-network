<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Illuminate\Database\Eloquent\Model;

interface AuthorizesApplicationAdmin
{
    public function canAdministerApplications(?Model $user, AffiliateOfferApplication $application): bool;
}
