<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Contracts\LinkedProgramBridge;
use AIArmada\AffiliateNetwork\Data\NetworkMembership;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        private readonly ?LinkedProgramBridge $programs = null,
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

    public function isLocalProgramOffer(AffiliateOffer $offer): bool
    {
        if (empty($offer->external_program_id)) {
            return false;
        }

        /** @var array<string, mixed>|null $metadata */
        $metadata = $offer->metadata;

        return ($metadata['catalog_source'] ?? 'local') === 'local';
    }

    /**
     * Resolve the linked core program id for an imported offer.
     *
     * Null for remote offers, offers whose program vanished, and when no
     * program bridge is bound — all of which use the network flow instead.
     */
    private function linkedProgramId(AffiliateOffer $offer): ?string
    {
        if (! $this->isLocalProgramOffer($offer) || $this->programs === null) {
            return null;
        }

        $programId = (string) $offer->external_program_id;

        return $this->programs->existingProgramIds([$programId]) !== [] ? $programId : null;
    }

    private function membershipFor(string $affiliateId, string $programId): ?NetworkMembership
    {
        return $this->membershipsForPrograms($affiliateId, [$programId])[$programId] ?? null;
    }

    /**
     * Enroll an affiliate in an imported offer's existing core program.
     *
     * Null for remote-only offers, offers whose program vanished, and when
     * no program bridge is bound.
     */
    public function enrollInLinkedProgram(AffiliateOffer $offer, string $affiliateId): ?NetworkMembership
    {
        $programId = $this->linkedProgramId($offer);

        if ($programId === null) {
            return null;
        }

        return $this->programs?->join($affiliateId, $programId);
    }

    public function hasAppliedForOffer(AffiliateOffer $offer, string $affiliateId): bool
    {
        $programId = $this->linkedProgramId($offer);

        if ($programId !== null) {
            return $this->membershipFor($affiliateId, $programId) !== null;
        }

        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliateId)
            ->exists();
    }

    public function applicationStatusForOffer(AffiliateOffer $offer, string $affiliateId): ?string
    {
        $programId = $this->linkedProgramId($offer);

        if ($programId !== null) {
            return $this->membershipFor($affiliateId, $programId)?->status;
        }

        /** @var ApplicationStatus|string|null $status */
        $status = AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliateId)
            ->value('status');

        return $status instanceof ApplicationStatus ? $status->value : $status;
    }

    /**
     * Batch membership statuses for local-program offers.
     *
     * @param  array<int, string>  $programIds
     * @return array<string, NetworkMembership> Memberships keyed by program id.
     */
    public function membershipsForPrograms(string $affiliateId, array $programIds): array
    {
        if ($this->programs === null || $programIds === []) {
            return [];
        }

        return $this->programs->membershipsFor($affiliateId, $programIds);
    }

    /**
     * Batch per-offer application statuses in a fixed handful of queries
     * (programs + memberships + applications) instead of 1–3 per offer.
     *
     * @param  Collection<int, AffiliateOffer>  $offers
     * @return array<string, ?string> offer id => status value (null when never applied)
     */
    public function applicationStatusMap(string $affiliateId, Collection $offers): array
    {
        /** @var array<string, array<int, string>> $programOfferIds program id => offer ids */
        $programOfferIds = [];
        /** @var array<int, string> $networkOfferIds */
        $networkOfferIds = [];

        /** @var array<string, ?string> $map */
        $map = [];

        foreach ($offers as $offer) {
            $offerId = (string) $offer->getKey();
            $map[$offerId] = null;

            if ($this->isLocalProgramOffer($offer)) {
                $programOfferIds[(string) $offer->external_program_id][] = $offerId;
            } else {
                $networkOfferIds[] = $offerId;
            }
        }

        if ($programOfferIds !== []) {
            $existing = array_fill_keys(
                $this->programs?->existingProgramIds(array_keys($programOfferIds)) ?? [],
                true
            );

            $statusByProgram = [];

            foreach ($this->membershipsForPrograms($affiliateId, array_keys($existing)) as $programId => $membership) {
                $statusByProgram[$programId] = $membership->status;
            }

            foreach ($programOfferIds as $programId => $offerIds) {
                if (! isset($existing[$programId])) {
                    // Missing local program (or no bridge): same fallback as
                    // everywhere else — resolve through the network flow.
                    array_push($networkOfferIds, ...$offerIds);

                    continue;
                }

                foreach ($offerIds as $offerId) {
                    $map[$offerId] = $statusByProgram[$programId] ?? null;
                }
            }
        }

        if ($networkOfferIds !== []) {
            foreach (AffiliateOfferApplication::query()
                ->where('affiliate_id', $affiliateId)
                ->whereIn('offer_id', $networkOfferIds)
                ->pluck('status', 'offer_id') as $offerId => $status) {
                $map[(string) $offerId] = $status instanceof ApplicationStatus ? $status->value : (string) $status;
            }
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
        $programId = $this->linkedProgramId($offer);

        if ($programId !== null) {
            return $this->membershipFor($affiliateId, $programId)?->isApproved() ?? false;
        }

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

        /** @var array<int, string> $approvedProgramIds */
        $approvedProgramIds = $this->programs?->approvedProgramIds($affiliateId, $limit) ?? [];

        $offers = AffiliateOffer::query()
            ->where('status', OfferStatus::Published)
            ->where(function (Builder $query) use ($approvedOfferIds, $approvedProgramIds): void {
                $query->whereIn('id', $approvedOfferIds);

                if ($approvedProgramIds !== []) {
                    $query->orWhereIn('external_program_id', $approvedProgramIds);
                }
            })
            ->limit($limit)
            ->get();

        /** @var array<int, string> $importedProgramIds */
        $importedProgramIds = $offers
            ->map(fn (AffiliateOffer $offer): ?string => $offer->external_program_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var array<int, string> $existingProgramIds */
        $existingProgramIds = $importedProgramIds === [] || $this->programs === null
            ? []
            : $this->programs->existingProgramIds($importedProgramIds);

        // Local imports delegate approval to the linked core program;
        // remote mirrors and offers whose program vanished keep the
        // network application flow, mirroring isApprovedForOffer().
        return $offers
            ->filter(fn (AffiliateOffer $offer): bool => in_array((string) $offer->getKey(), $approvedOfferIds, true)
                || ($this->isLocalProgramOffer($offer)
                    && $offer->external_program_id !== null
                    && in_array($offer->external_program_id, $existingProgramIds, true)))
            ->take($limit)
            ->values();
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
