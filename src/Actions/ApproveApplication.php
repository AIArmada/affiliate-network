<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ApproveApplication
{
    public function execute(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application = AffiliateOfferApplication::query()
            ->whereKey($application->getKey())
            ->firstOrFail();

        // Verification is a network fact, not tenant data: resolve the offer
        // unscoped so cross-owner approvals are judged on the site's real
        // status instead of the ambient scope. Ownership was already
        // established by the scoped re-query above.
        $offer = AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->whereKey($application->offer_id)
            ->first();

        if (! $offer instanceof AffiliateOffer || ! AffiliateSite::isVerifiedKey($offer->site_id)) {
            throw new RuntimeException('Applications can only be approved for verified sites.');
        }

        $application->update([
            'status' => ApplicationStatus::Approved,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => CarbonImmutable::now(),
            'approved_at' => CarbonImmutable::now(),
        ]);

        $fresh = $application->fresh() ?? $application;

        event(new ApplicationApproved($fresh));

        return $fresh;
    }
}
