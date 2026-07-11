<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * Global admin operations across all tenants.
 *
 * BOUNDARY: Explicitly bypasses owner scoping via OwnerContext::withOwner(null, ...)
 * and removes named scopes from queries. Destructive operations include audit logging.
 * Intended for platform administrators and system operations only.
 */
final class GlobalAdminService
{
    /**
     * @return Collection<int, AffiliateOffer>
     */
    public function getAllOffers(): Collection
    {
        return OwnerContext::withOwner(null, fn (): Collection => AffiliateOffer::query()
            ->withoutGlobalScope('owner_via_site')
            ->get());
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getOffer(string $offerId): AffiliateOffer
    {
        return OwnerContext::withOwner(null, fn (): AffiliateOffer => AffiliateOffer::query()
            ->withoutGlobalScope('owner_via_site')
            ->findOrFail($offerId));
    }

    /**
     * @return Collection<int, AffiliateOfferApplication>
     */
    public function getAllApplications(): Collection
    {
        return OwnerContext::withOwner(null, fn (): Collection => AffiliateOfferApplication::query()
            ->withoutGlobalScope('owner_via_affiliate')
            ->get());
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getApplication(string $applicationId): AffiliateOfferApplication
    {
        return OwnerContext::withOwner(null, fn (): AffiliateOfferApplication => AffiliateOfferApplication::query()
            ->withoutGlobalScope('owner_via_affiliate')
            ->findOrFail($applicationId));
    }

    /**
     * @return Collection<int, AffiliateSite>
     */
    public function getAllSites(): Collection
    {
        return OwnerContext::withOwner(null, fn (): Collection => AffiliateSite::query()
            ->withoutOwnerScope()
            ->get());
    }

    /**
     * @throws ModelNotFoundException
     */
    public function forceDeleteOffer(string $offerId): void
    {
        OwnerContext::withOwner(null, function () use ($offerId): void {
            $offer = AffiliateOffer::query()
                ->withoutGlobalScope('owner_via_site')
                ->findOrFail($offerId);

            $offerName = $offer->name;

            $offer->delete();

            Log::info('[GlobalAdmin] Offer force-deleted', [
                'offer_id' => $offerId,
                'offer_name' => $offerName,
            ]);
        });
    }

    /**
     * @throws ModelNotFoundException
     */
    public function forceDeleteApplication(string $applicationId): void
    {
        OwnerContext::withOwner(null, function () use ($applicationId): void {
            $application = AffiliateOfferApplication::query()
                ->withoutGlobalScope('owner_via_affiliate')
                ->findOrFail($applicationId);

            $application->delete();

            Log::info('[GlobalAdmin] Application force-deleted', [
                'application_id' => $applicationId,
            ]);
        });
    }
}
