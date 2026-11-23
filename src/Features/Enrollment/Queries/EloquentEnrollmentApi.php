<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Queries;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\DeleteOfferEnrollments;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\DealRateOverride;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use Carbon\CarbonImmutable;

final class EloquentEnrollmentApi implements EnrollmentApi
{
    public function __construct(
        private readonly ApplicationStatusQuery $query,
        private readonly DeleteOfferEnrollments $deleteOfferEnrollments,
    ) {}

    public function isApprovedForOffer(string $offerId, string $affiliateId): bool
    {
        return $this->query->isApprovedForOffer($offerId, $affiliateId);
    }

    public function isApprovedForOfferUnscoped(string $offerId, string $affiliateId): bool
    {
        return $this->query->isApprovedForOfferUnscoped($offerId, $affiliateId);
    }

    public function hasAppliedForOffer(string $offerId, string $affiliateId): bool
    {
        return $this->query->hasAppliedForOffer($offerId, $affiliateId);
    }

    public function applicationStatusForOffer(string $offerId, string $affiliateId): ?ApplicationStatus
    {
        return $this->query->applicationStatusForOffer($offerId, $affiliateId);
    }

    public function applicationStatusMap(string $affiliateId, array $offerIds): array
    {
        return $this->query->applicationStatusMap($affiliateId, $offerIds);
    }

    public function approvedOfferIds(string $affiliateId, int $limit = 500): array
    {
        return $this->query->approvedOfferIds($affiliateId, $limit);
    }

    public function activeDealFor(string $offerId, string $affiliateId, ?CarbonImmutable $at = null): ?DealRateOverride
    {
        return $this->query->activeDealFor($offerId, $affiliateId, $at);
    }

    public function deleteForOffer(string $offerId): void
    {
        $this->deleteOfferEnrollments->execute($offerId);
    }

    public function averageApprovalHoursForOffer(string $offerId, CarbonImmutable $since): ?float
    {
        return $this->query->averageApprovalHoursForOffer($offerId, $since);
    }
}
