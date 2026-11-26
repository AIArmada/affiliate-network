<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Api;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use Carbon\CarbonImmutable;

interface EnrollmentApi
{
    public function isApprovedForOffer(string $offerId, string $affiliateId): bool;

    public function isApprovedForOfferUnscoped(string $offerId, string $affiliateId): bool;

    public function hasAppliedForOffer(string $offerId, string $affiliateId): bool;

    public function applicationStatusForOffer(string $offerId, string $affiliateId): ?ApplicationStatus;

    /**
     * @param  array<int, string>  $offerIds
     * @return array<string, ApplicationStatus|null>
     */
    public function applicationStatusMap(string $affiliateId, array $offerIds): array;

    /**
     * @return array<int, string>
     */
    public function approvedOfferIds(string $affiliateId, int $limit = 500): array;

    public function activeDealFor(string $offerId, string $affiliateId, ?CarbonImmutable $at = null): ?DealRateOverride;

    public function deleteForOffer(string $offerId): void;

    public function averageApprovalHoursForOffer(string $offerId, CarbonImmutable $since): ?float;

    public function countPendingApplicationsUnscoped(): int;

    /** @return list<RecentApplication> */
    public function recentPendingApplications(int $limit = 5): array;
}
