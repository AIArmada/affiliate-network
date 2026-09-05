<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Carbon\CarbonImmutable;

final class ApproveApplication
{
    public function execute(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application = AffiliateOfferApplication::query()
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->update([
            'status' => ApplicationStatus::Approved,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => CarbonImmutable::now(),
            'approved_at' => CarbonImmutable::now(),
        ]);

        $fresh = $application->fresh();

        event(new ApplicationApproved($fresh));

        return $fresh;
    }
}
