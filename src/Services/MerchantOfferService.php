<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Actions\DeleteOffer;
use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationRejected;
use AIArmada\AffiliateNetwork\Events\ApplicationRevoked;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\ApprovedState;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\RejectedState;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\RevokedState;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Merchant-scoped operations for offers and applications.
 *
 * BOUNDARY: Wraps all queries in the merchant's owner context so offers
 * are scoped to the merchant's own sites. Applications to the merchant's
 * offers bypass the affiliate-owner scope intentionally — a merchant
 * reviews applications to their offers, not "their" applications.
 */
final class MerchantOfferService
{
    public function __construct(
        private readonly CreateOffer $createOfferAction,
        private readonly UpdateOffer $updateOfferAction,
        private readonly DeleteOffer $deleteOfferAction,
    ) {}

    /**
     * @return Collection<int, AffiliateOffer>
     */
    public function getOffers(Model $owner): Collection
    {
        return OwnerContext::withOwner($owner, fn (): Collection => AffiliateOffer::query()
            ->where('status', OfferStatus::Published)
            ->get());
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getOffer(Model $owner, string $offerId): AffiliateOffer
    {
        return OwnerContext::withOwner($owner, fn (): AffiliateOffer => AffiliateOffer::query()->findOrFail($offerId));
    }

    /**
     * @return Collection<int, AffiliateSite>
     */
    public function getSites(Model $owner): Collection
    {
        return OwnerContext::withOwner($owner, fn (): Collection => AffiliateSite::query()->get());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOffer(Model $owner, AffiliateSite $site, array $data): AffiliateOffer
    {
        return OwnerContext::withOwner($owner, fn (): AffiliateOffer => $this->createOfferAction->execute($site, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOffer(Model $owner, AffiliateOffer $offer, array $data): AffiliateOffer
    {
        return OwnerContext::withOwner($owner, fn (): AffiliateOffer => $this->updateOfferAction->execute($offer, $data));
    }

    public function deleteOffer(Model $owner, AffiliateOffer $offer): void
    {
        OwnerContext::withOwner($owner, function () use ($offer): void {
            $this->deleteOfferAction->execute($offer);
        });
    }

    /**
     * Get applications to the merchant's offers.
     *
     * Bypasses the affiliate-owner scope — a merchant reviews applications
     * across all affiliates who applied to their offers.
     *
     * @return Collection<int, AffiliateOfferApplication>
     */
    public function getApplications(Model $owner, ?string $offerId = null): Collection
    {
        return OwnerContext::withOwner($owner, function () use ($offerId): Collection {
            $offerIds = AffiliateOffer::query()->pluck('id');

            $query = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')
                ->whereIn('offer_id', $offerIds);

            if ($offerId !== null) {
                $query->where('offer_id', $offerId);
            }

            return $query->get();
        });
    }

    /**
     * @throws ModelNotFoundException
     */
    public function approveApplication(Model $owner, string $applicationId, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return OwnerContext::withOwner($owner, function () use ($applicationId, $reviewedBy): AffiliateOfferApplication {
            $application = $this->resolveApplicationForOwner($applicationId);

            $application->status->transitionTo(ApprovedState::class);

            $application->reviewed_by = $reviewedBy;
            $application->reviewed_at = CarbonImmutable::now();
            $application->approved_at = CarbonImmutable::now();
            $application->save();

            $fresh = $application->fresh();

            event(new ApplicationApproved($fresh));

            return $fresh;
        });
    }

    /**
     * @throws ModelNotFoundException
     */
    public function rejectApplication(Model $owner, string $applicationId, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return OwnerContext::withOwner($owner, function () use ($applicationId, $reason, $reviewedBy): AffiliateOfferApplication {
            $application = $this->resolveApplicationForOwner($applicationId);

            $application->status->transitionTo(RejectedState::class);

            $application->rejection_reason = $reason;
            $application->reviewed_by = $reviewedBy;
            $application->reviewed_at = CarbonImmutable::now();
            $application->rejected_at = CarbonImmutable::now();
            $application->save();

            $fresh = $application->fresh();

            event(new ApplicationRejected($fresh));

            return $fresh;
        });
    }

    /**
     * @throws ModelNotFoundException
     */
    public function revokeApplication(Model $owner, string $applicationId, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return OwnerContext::withOwner($owner, function () use ($applicationId, $reason, $reviewedBy): AffiliateOfferApplication {
            $application = $this->resolveApplicationForOwner($applicationId);

            $application->status->transitionTo(RevokedState::class);

            $application->rejection_reason = $reason;
            $application->reviewed_by = $reviewedBy;
            $application->reviewed_at = CarbonImmutable::now();
            $application->revoked_at = CarbonImmutable::now();
            $application->save();

            $fresh = $application->fresh();

            event(new ApplicationRevoked($fresh));

            return $fresh;
        });
    }

    /**
     * Resolve application, verifying the linked offer belongs to this merchant's scope.
     *
     * @throws ModelNotFoundException
     */
    private function resolveApplicationForOwner(string $applicationId): AffiliateOfferApplication
    {
        $offerIds = AffiliateOffer::query()->pluck('id');

        /** @var AffiliateOfferApplication|null $application */
        $application = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')
            ->whereKey($applicationId)
            ->whereIn('offer_id', $offerIds)
            ->first();

        if ($application === null) {
            throw (new ModelNotFoundException)->setModel(AffiliateOfferApplication::class, [$applicationId]);
        }

        return $application;
    }
}
