<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Offer Management Service — offer lifecycle.
 *
 * BOUNDARY: This service owns offer creation, applications, approvals,
 * rejections, and offer visibility. It does NOT handle URL signing, redirects,
 * click attribution, or conversion recording (see OfferLinkService).
 *
 * @see OfferLinkService for link lifecycle operations.
 */
final class OfferManagementService
{
    public function __construct(
        private readonly CreateOffer $createOfferAction,
        private readonly ApplyToOffer $applyToOfferAction,
        private readonly ApproveApplication $approveApplicationAction,
    ) {}

    /**
     * Create a new offer for a site.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOffer(AffiliateSite $site, array $data): AffiliateOffer
    {
        return $this->createOfferAction->execute($site, $data);
    }

    /**
     * Apply for an offer as an affiliate.
     */
    public function applyForOffer(AffiliateOffer $offer, string $affiliateId, ?string $reason = null): AffiliateOfferApplication
    {
        return $this->applyToOfferAction->execute($offer, $affiliateId, $reason);
    }

    public function hasAppliedForOffer(AffiliateOffer $offer, string $affiliateId): bool
    {
        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliateId)
            ->exists();
    }

    public function applicationStatusForOffer(AffiliateOffer $offer, string $affiliateId): ?string
    {
        /** @var ApplicationStatus|string|null $status */
        $status = AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliateId)
            ->value('status');

        return $status instanceof ApplicationStatus ? $status->value : $status;
    }

    /**
     * Batch per-offer application statuses in one query instead of one
     * per offer.
     *
     * @param  Collection<int, AffiliateOffer>  $offers
     * @return array<string, ?string> offer id => status value (null when never applied)
     */
    public function applicationStatusMap(string $affiliateId, Collection $offers): array
    {
        /** @var array<string, ?string> $map */
        $map = [];

        /** @var array<int, string> $offerIds */
        $offerIds = [];

        foreach ($offers as $offer) {
            $offerId = (string) $offer->getKey();
            $map[$offerId] = null;
            $offerIds[] = $offerId;
        }

        if ($offerIds === []) {
            return $map;
        }

        foreach (AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('offer_id', $offerIds)
            ->pluck('status', 'offer_id') as $offerId => $status) {
            $map[(string) $offerId] = $status instanceof ApplicationStatus ? $status->value : (string) $status;
        }

        return $map;
    }

    /**
     * Approve an application.
     */
    public function approveApplication(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return $this->approveApplicationAction->execute($application, $reviewedBy);
    }

    /**
     * Reject an application.
     */
    public function rejectApplication(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application = AffiliateOfferApplication::query()
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->update([
            'status' => ApplicationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => CarbonImmutable::now(),
            'rejected_at' => CarbonImmutable::now(),
        ]);

        return $application->fresh() ?? $application;
    }

    /**
     * Revoke an approved application.
     */
    public function revokeApplication(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application = AffiliateOfferApplication::query()
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->update([
            'status' => ApplicationStatus::Revoked,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => CarbonImmutable::now(),
            'revoked_at' => CarbonImmutable::now(),
        ]);

        return $application->fresh() ?? $application;
    }

    /**
     * Check if an affiliate is approved for an offer.
     */
    public function isApprovedForOffer(AffiliateOffer $offer, string $affiliateId): bool
    {
        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliateId)
            ->where('status', ApplicationStatus::Approved)
            ->exists();
    }

    /**
     * Get all offers an affiliate is approved for.
     *
     * @return EloquentCollection<int, AffiliateOffer>
     */
    public function getApprovedOffers(string $affiliateId, int $limit = 500): EloquentCollection
    {
        $limit = max(1, $limit);

        /** @var array<int, string> $approvedOfferIds */
        $approvedOfferIds = AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', ApplicationStatus::Approved)
            ->limit($limit)
            ->pluck('offer_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        if ($approvedOfferIds === []) {
            return new EloquentCollection;
        }

        return AffiliateOffer::query()
            ->where('status', OfferStatus::Published)
            ->whereIn('id', $approvedOfferIds)
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve a marketplace offer by ID with explicit public/active guards.
     *
     * This is a public marketplace endpoint — bypasses per-site owner scope intentionally.
     *
     * @throws ModelNotFoundException
     */
    public function resolvePublicOfferOrFail(string $offerId): AffiliateOffer
    {
        // Public marketplace lookup intentionally runs outside merchant scope;
        // mutating callers re-enter the affiliate or merchant owner context.
        return OwnerContext::withOwner(null, fn (): AffiliateOffer => AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->whereKey($offerId)
            ->where('status', OfferStatus::Published)
            ->where('visibility', OfferVisibility::Public)
            ->whereSiteVerified()
            ->firstOrFail());
    }
}
