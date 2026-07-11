<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Contracts\AuthorizesApplicationAdmin;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Exceptions\UnauthorizedApplicationAdminException;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\RejectedState;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\RevokedState;
use AIArmada\Affiliates\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
     * Approve an application.
     */
    public function approveApplication(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return $this->approveApplicationAction->execute($application, $reviewedBy);
    }

    /**
     * Reject an application.
     */
    public function rejectApplication(
        AffiliateOfferApplication $application,
        string $reason,
        ?string $reviewedBy = null,
        ?Model $authorizedBy = null,
    ): AffiliateOfferApplication {
        if ($authorizedBy !== null) {
            $authorizer = app(AuthorizesApplicationAdmin::class);

            if (! $authorizer->canAdministerApplications($authorizedBy, $application)) {
                throw new UnauthorizedApplicationAdminException();
            }
        }

        $application = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->status->transitionTo(RejectedState::class);
        $application->rejection_reason = $reason;
        $application->reviewed_by = $reviewedBy;
        $application->reviewed_at = CarbonImmutable::now();
        $application->rejected_at = CarbonImmutable::now();
        $application->save();

        return $application->fresh();
    }

    /**
     * Revoke an approved application.
     */
    public function revokeApplication(
        AffiliateOfferApplication $application,
        string $reason,
        ?string $reviewedBy = null,
        ?Model $authorizedBy = null,
    ): AffiliateOfferApplication {
        if ($authorizedBy !== null) {
            $authorizer = app(AuthorizesApplicationAdmin::class);

            if (! $authorizer->canAdministerApplications($authorizedBy, $application)) {
                throw new UnauthorizedApplicationAdminException();
            }
        }

        $application = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')
            ->whereKey($application->getKey())
            ->firstOrFail();

        $application->status->transitionTo(RevokedState::class);
        $application->rejection_reason = $reason;
        $application->reviewed_by = $reviewedBy;
        $application->reviewed_at = CarbonImmutable::now();
        $application->revoked_at = CarbonImmutable::now();
        $application->save();

        return $application->fresh();
    }

    /**
     * Check if an affiliate is approved for an offer.
     */
    public function isApprovedForOffer(AffiliateOffer $offer, Affiliate $affiliate): bool
    {
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
    public function getApprovedOffers(Affiliate $affiliate): Collection
    {
        $approvedOfferIds = AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', ApplicationStatus::Approved)
            ->pluck('offer_id');

        return AffiliateOffer::query()
            ->whereIn('id', $approvedOfferIds)
            ->where('status', OfferStatus::Published)
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
        return AffiliateOffer::withoutGlobalScope('owner_via_site')
            ->whereKey($offerId)
            ->where('status', OfferStatus::Published)
            ->where('visibility', OfferVisibility::Public)
            ->firstOrFail();
    }
}
