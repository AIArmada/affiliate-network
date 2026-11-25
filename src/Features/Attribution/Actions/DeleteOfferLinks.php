<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\Links\Models\Link;

final class DeleteOfferLinks
{
    public function execute(string $offerId): void
    {
        $linkIds = AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->whereNotNull('link_id')
            ->pluck('link_id')
            ->filter(static fn (mixed $linkId): bool => is_string($linkId) && $linkId !== '')
            ->values()
            ->all();

        AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        AffiliateOfferCoupon::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        if ($linkIds === []) {
            return;
        }

        Link::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $linkIds)
            ->get()
            ->each(static fn (Link $link) => $link->delete());
    }
}
