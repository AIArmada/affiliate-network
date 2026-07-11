<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Affiliate-scoped operations for marketplace and applications.
 *
 * BOUNDARY: Views the marketplace across all merchants but scopes own
 * applications and links to the affiliate's owner context.
 * Does NOT expose merchant-write operations (create/update/delete offer).
 */
final class AffiliateOfferService
{
    public function __construct(
        private readonly ApplyToOffer $applyToOfferAction,
    ) {}

    /**
     * Get all published public offers visible in the marketplace.
     *
     * Bypasses the site-owner scope intentionally — an affiliate needs to
     * see offers from ALL merchants in the marketplace.
     *
     * @return Collection<int, AffiliateOffer>
     */
    public function getMarketplaceOffers(Affiliate $affiliate): Collection
    {
        return app(AffiliateOfferEligibility::class)
            ->applyToQuery(
                AffiliateOffer::withoutGlobalScope('owner_via_site'),
                requirePublicVisibility: true,
            )
            ->get();
    }

    /**
     * Apply to a marketplace offer as an affiliate.
     */
    public function applyToOffer(Affiliate $affiliate, string $offerId, ?string $reason = null): AffiliateOfferApplication
    {
        $offer = $this->resolvePublicOfferOrFail($offerId);

        return $this->applyToOfferAction->execute($offer, $affiliate, $reason);
    }

    /**
     * Get applications submitted by this affiliate.
     *
     * @return Collection<int, AffiliateOfferApplication>
     */
    public function getMyApplications(Affiliate $affiliate): Collection
    {
        return AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliate->id)
            ->get();
    }

    /**
     * Get offers the affiliate is approved for.
     *
     * @return Collection<int, AffiliateOffer>
     */
    public function getMyApprovedOffers(Affiliate $affiliate): Collection
    {
        $approvedOfferIds = AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', ApplicationStatus::Approved)
            ->pluck('offer_id');

        return app(AffiliateOfferEligibility::class)
            ->applyToQuery(
                AffiliateOffer::withoutGlobalScope('owner_via_site')->whereIn('id', $approvedOfferIds),
                requirePublicVisibility: false,
            )
            ->get();
    }

    /**
     * @throws ModelNotFoundException
     */
    private function resolvePublicOfferOrFail(string $offerId): AffiliateOffer
    {
        /** @var AffiliateOffer|null $offer */
        $offer = app(AffiliateOfferEligibility::class)
            ->applyToQuery(
                AffiliateOffer::withoutGlobalScope('owner_via_site')->whereKey($offerId),
                requirePublicVisibility: true,
            )
            ->first();

        if ($offer === null) {
            throw (new ModelNotFoundException)->setModel(AffiliateOffer::class, [$offerId]);
        }

        return $offer;
    }
}
