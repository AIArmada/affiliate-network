<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Queries;

use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;

final class ResolvePublicOfferQuery
{
    public function find(string $offerId): ?AffiliateOffer
    {
        return AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->whereKey($offerId)
            ->where('status', OfferStatus::Published)
            ->where('visibility', OfferVisibility::Public)
            ->whereSiteVerified()
            ->first();
    }
}
