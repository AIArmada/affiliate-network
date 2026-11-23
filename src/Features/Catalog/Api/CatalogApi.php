<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Api;

use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use Carbon\CarbonImmutable;

interface CatalogApi
{
    public function findOfferUnscoped(string $offerId): ?OfferRecord;

    public function findCreativeUnscoped(string $creativeId): ?CreativeRecord;

    public function findPublicOffer(string $offerId): ?OfferRecord;

    public function wasOfferActiveAt(string $offerId, CarbonImmutable $at): bool;

    public function evaluateRestrictions(OfferRecord $offer, RestrictionContext $context): ?string;

    /**
     * @return array<string, mixed>|null
     */
    public function evaluateActivePromotion(OfferRecord $offer, CarbonImmutable $at): ?array;

    public function deleteOffersForSite(string $siteId): void;

    public function fallbackUrlForOffer(string $offerId): ?string;
}
