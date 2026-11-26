<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Queries;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\DeleteOfferEnrollments;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\DealRateOverride;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\RecentApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Carbon\CarbonImmutable;

final class EloquentEnrollmentApi implements EnrollmentApi
{
    public function __construct(
        private readonly ApplicationStatusQuery $query,
        private readonly DeleteOfferEnrollments $deleteOfferEnrollments,
        private readonly CatalogApi $catalog,
        private readonly AffiliateIdentityResolver $identities,
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

    public function countPendingApplicationsUnscoped(): int
    {
        return AffiliateOfferApplication::withoutGlobalScope(ScopesByBelongsToOwner::class)->where('status', ApplicationStatus::Pending)->count();
    }

    public function recentPendingApplications(int $limit = 5): array
    {
        $applications = AffiliateOfferApplication::query()
            ->where('status', ApplicationStatus::Pending)
            ->latest('created_at')
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->get(['id', 'offer_id', 'affiliate_id', 'created_at']);

        $offers = [];
        $affiliates = [];
        $result = [];

        foreach ($applications as $application) {
            $offerId = (string) $application->offer_id;
            $affiliateId = (string) $application->affiliate_id;

            if (! array_key_exists($offerId, $offers)) {
                $offers[$offerId] = $this->catalog->findOfferUnscoped($offerId)?->name;
            }

            if (! array_key_exists($affiliateId, $affiliates)) {
                $affiliates[$affiliateId] = $this->identities->findAccessible($affiliateId)?->code;
            }

            $result[] = new RecentApplication(
                (string) $application->getKey(),
                $offers[$offerId],
                $affiliates[$affiliateId],
                $application->created_at,
            );
        }

        return $result;
    }
}
