<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Contracts\AuthorizesApplicationAdmin;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Exceptions\UnauthorizedApplicationAdminException;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\ApprovedState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class ApproveApplication
{
    public function execute(
        AffiliateOfferApplication $application,
        ?string $reviewedBy = null,
        null|Model|bool $authorizedBy = false,
    ): AffiliateOfferApplication {
        if ($authorizedBy !== false) {
            $authorizer = app(AuthorizesApplicationAdmin::class);

            if (! $authorizer->canAdministerApplications($authorizedBy, $application)) {
                throw new UnauthorizedApplicationAdminException();
            }
        }

        $application = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->status->transitionTo(ApprovedState::class);
        $application->reviewed_by = $reviewedBy;
        $application->reviewed_at = CarbonImmutable::now();
        $application->approved_at = CarbonImmutable::now();
        $application->save();

        $fresh = $application->fresh();

        event(new ApplicationApproved($fresh));

        return $fresh;
    }
}
