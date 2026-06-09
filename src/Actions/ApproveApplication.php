<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Carbon\CarbonImmutable;

final class ApproveApplication
{
    public function execute(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->update([
            'status' => AffiliateOfferApplication::STATUS_APPROVED,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => CarbonImmutable::now(),
        ]);

        $fresh = $application->fresh();

        event(new ApplicationApproved($fresh));

        return $fresh;
    }
}
