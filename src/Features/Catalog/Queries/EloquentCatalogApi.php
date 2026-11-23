<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Queries;

use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\DeleteOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CreativeRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;

final class EloquentCatalogApi implements CatalogApi
{
    public function __construct(
        private readonly ResolvePublicOfferQuery $publicOffers,
        private readonly EvaluateOfferRestrictions $restrictions,
        private readonly EvaluateActivePromotion $promotions,
        private readonly SitesApi $sites,
    ) {}

    public function findOfferUnscoped(string $offerId): ?OfferRecord
    {
        return AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->whereKey($offerId)
            ->first()
            ?->toRecord();
    }

    public function findCreativeUnscoped(string $creativeId): ?CreativeRecord
    {
        return AffiliateOfferCreative::query()
            ->withoutGlobalScopes()
            ->whereKey($creativeId)
            ->first()
            ?->toRecord();
    }

    public function findPublicOffer(string $offerId): ?OfferRecord
    {
        return $this->publicOffers->find($offerId)?->toRecord();
    }

    public function wasOfferActiveAt(string $offerId, CarbonImmutable $at): bool
    {
        $offer = AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->whereKey($offerId)
            ->first();

        return $offer instanceof AffiliateOffer && $offer->wasActiveAt($at);
    }

    public function evaluateRestrictions(OfferRecord $offer, RestrictionContext $context): ?string
    {
        return $this->restrictions->evaluate($offer, $context);
    }

    public function evaluateActivePromotion(OfferRecord $offer, CarbonImmutable $at): ?array
    {
        return $this->promotions->evaluate($offer, $at);
    }

    public function deleteOffersForSite(string $siteId): void
    {
        $offers = AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->get();

        foreach ($offers as $offer) {
            app(DeleteOffer::class)->execute($offer);
        }
    }

    public function fallbackUrlForOffer(string $offerId): ?string
    {
        $offer = AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->whereKey($offerId)
            ->first();

        if (! $offer instanceof AffiliateOffer) {
            return null;
        }

        /** @var AffiliateOffer $offer */
        $fallbackUrl = $offer->getAttribute('fallback_url');

        if (is_string($fallbackUrl) && $fallbackUrl !== '') {
            return $fallbackUrl;
        }

        $fallbackOfferId = $offer->getAttribute('fallback_offer_id');

        if (is_string($fallbackOfferId) && $fallbackOfferId !== '') {
            $fallback = AffiliateOffer::query()
                ->withoutGlobalScopes()
                ->whereKey($fallbackOfferId)
                ->first();

            if ($fallback instanceof AffiliateOffer
                && $fallback->isActive()
                && $this->sites->isVerified((string) $fallback->site_id)) {
                $landing = $fallback->getAttribute('landing_url');

                if (is_string($landing) && $landing !== '') {
                    return $landing;
                }
            }
        }

        $default = config('affiliate-network.links.default_fallback_url');

        return is_string($default) && $default !== '' ? $default : null;
    }
}
