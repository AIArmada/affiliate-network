<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Queries;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\DealRateOverride;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use Carbon\CarbonImmutable;

final class ApplicationStatusQuery
{
    public function isApprovedForOffer(string $offerId, string $affiliateId): bool
    {
        return AffiliateOfferApplication::query()
            ->where('offer_id', $offerId)
            ->where('affiliate_id', $affiliateId)
            ->where('status', ApplicationStatus::Approved)
            ->exists();
    }

    public function isApprovedForOfferUnscoped(string $offerId, string $affiliateId): bool
    {
        return AffiliateOfferApplication::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->where('affiliate_id', $affiliateId)
            ->where('status', ApplicationStatus::Approved)
            ->exists();
    }

    public function hasAppliedForOffer(string $offerId, string $affiliateId): bool
    {
        return AffiliateOfferApplication::query()
            ->where('offer_id', $offerId)
            ->where('affiliate_id', $affiliateId)
            ->exists();
    }

    public function applicationStatusForOffer(string $offerId, string $affiliateId): ?ApplicationStatus
    {
        $status = AffiliateOfferApplication::query()
            ->where('offer_id', $offerId)
            ->where('affiliate_id', $affiliateId)
            ->value('status');

        return $this->normalizeStatus($status);
    }

    /**
     * @param  array<int, string>  $offerIds
     * @return array<string, ApplicationStatus|null>
     */
    public function applicationStatusMap(string $affiliateId, array $offerIds): array
    {
        $map = [];

        foreach ($offerIds as $offerId) {
            $map[(string) $offerId] = null;
        }

        if ($map === []) {
            return $map;
        }

        foreach (AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('offer_id', array_keys($map))
            ->pluck('status', 'offer_id') as $offerId => $status) {
            $map[(string) $offerId] = $this->normalizeStatus($status);
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public function approvedOfferIds(string $affiliateId, int $limit = 500): array
    {
        $limit = max(1, $limit);

        return AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', ApplicationStatus::Approved)
            ->limit($limit)
            ->pluck('offer_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function activeDealFor(string $offerId, string $affiliateId, ?CarbonImmutable $at = null): ?DealRateOverride
    {
        $at ??= CarbonImmutable::now();

        $deals = AffiliateOfferDeal::query()
            ->where('offer_id', $offerId)
            ->where('affiliate_id', $affiliateId)
            ->where('is_active', true)
            ->get();

        $match = null;

        foreach ($deals as $deal) {
            if (! $deal instanceof AffiliateOfferDeal || ! $deal->isActiveAt($at)) {
                continue;
            }

            if ($match === null || ($deal->starts_at !== null && ($match->starts_at === null || $deal->starts_at->gt($match->starts_at)))) {
                $match = $deal;
            }
        }

        return $match?->toOverride();
    }

    public function averageApprovalHoursForOffer(string $offerId, CarbonImmutable $since): ?float
    {
        $applications = AffiliateOfferApplication::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->where('status', ApplicationStatus::Approved)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $since)
            ->get(['created_at', 'approved_at']);

        if ($applications->isEmpty()) {
            return null;
        }

        $total = 0.0;

        foreach ($applications as $application) {
            if ($application->created_at === null || $application->approved_at === null) {
                continue;
            }

            $total += $application->created_at->diffInMinutes($application->approved_at) / 60;
        }

        return $total / $applications->count();
    }

    private function normalizeStatus(mixed $status): ?ApplicationStatus
    {
        if ($status instanceof ApplicationStatus) {
            return $status;
        }

        return is_string($status) ? ApplicationStatus::tryFrom($status) : null;
    }
}
