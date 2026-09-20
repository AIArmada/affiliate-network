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
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        private readonly ProgramService $programService,
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
    public function applyForOffer(AffiliateOffer $offer, Affiliate $affiliate, ?string $reason = null): AffiliateOfferApplication
    {
        return $this->applyToOfferAction->execute($offer, $affiliate, $reason);
    }

    /**
     * Resolve the local core program linked by an imported offer.
     *
     * A missing local program means the offer is a remote discovery record and
     * must use the network application flow instead.
     */
    public function linkedProgram(AffiliateOffer $offer): ?AffiliateProgram
    {
        if (! $this->isLocalProgramOffer($offer)) {
            return null;
        }

        return AffiliateProgram::query()->whereKey($offer->external_program_id)->first();
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
     * Enroll an affiliate in an imported offer's existing core program.
     *
     * @return AffiliateProgramMembership|null Null for remote-only offers.
     */
    public function enrollInLinkedProgram(AffiliateOffer $offer, Affiliate $affiliate): ?AffiliateProgramMembership
    {
        $program = $this->linkedProgram($offer);

        return $program === null ? null : $this->programService->joinProgram($affiliate, $program);
    }

    public function hasAppliedForOffer(AffiliateOffer $offer, Affiliate $affiliate): bool
    {
        $program = $this->linkedProgram($offer);

        if ($program !== null) {
            return $this->programService->getMembership($affiliate, $program) !== null;
        }

        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->exists();
    }

    public function applicationStatusForOffer(AffiliateOffer $offer, Affiliate $affiliate): ?string
    {
        $program = $this->linkedProgram($offer);

        if ($program !== null) {
            return $this->programService->getMembership($affiliate, $program)?->status->value;
        }

        /** @var ApplicationStatus|string|null $status */
        $status = AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->value('status');

        return $status instanceof ApplicationStatus ? $status->value : $status;
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
    public function isApprovedForOffer(AffiliateOffer $offer, Affiliate $affiliate): bool
    {
        $program = $this->linkedProgram($offer);

        if ($program !== null) {
            return $this->programService->isMember($affiliate, $program);
        }

        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->where('status', ApplicationStatus::Approved)
            ->exists();
    }

    /**
     * Get all offers an affiliate is approved for.
     *
     * @return Collection<int, AffiliateOffer>
     */
    public function getApprovedOffers(Affiliate $affiliate, int $limit = 500): Collection
    {
        $limit = max(1, $limit);

        /** @var array<int, string> $approvedOfferIds */
        $approvedOfferIds = AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', ApplicationStatus::Approved)
            ->limit($limit)
            ->pluck('offer_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        /** @var array<int, string> $approvedProgramIds */
        $approvedProgramIds = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', MembershipStatus::Approved)
            ->limit($limit)
            ->pluck('program_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

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
        $existingProgramIds = $importedProgramIds === []
            ? []
            : AffiliateProgram::query()
                ->whereIn('id', $importedProgramIds)
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

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
            ->firstOrFail());
    }
}
