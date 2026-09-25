<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;

/**
 * Network policy for tracked-link redirects.
 *
 * Ignores links that belong to other subjects; offer links redirect only
 * while the link, offer, site, and approval state all hold.
 */
final class OfferLinkGate implements LinkGateInterface
{
    public function __construct(
        private readonly OfferManagementService $offers,
    ) {}

    public function blockedReason(Link $link): ?string
    {
        if ($link->subject_type !== (new AffiliateOfferLink)->getMorphClass()) {
            return null;
        }

        // Public redirects resolve without an owner context; verification is
        // a network fact checked on keys so ambient scope can never change it.
        $offerLink = AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->with([
                'offer' => fn ($query) => $query->withoutGlobalScope(ScopesByBelongsToOwner::class),
            ])
            ->find($link->subject_id);

        if (! $offerLink instanceof AffiliateOfferLink) {
            return 'link_unknown';
        }

        if (! $offerLink->is_active) {
            return 'link_inactive';
        }

        if (! $offerLink->offer->isActive()) {
            return 'offer_inactive';
        }

        $linkSiteVerified = $offerLink->site_id !== null && AffiliateSite::isVerifiedKey($offerLink->site_id);
        $offerSiteVerified = AffiliateSite::isVerifiedKey($offerLink->offer->site_id);

        if (! $linkSiteVerified && ! $offerSiteVerified) {
            return 'site_unverified';
        }

        if ($offerLink->offer->requires_approval && ! $this->offers->isApprovedForOffer($offerLink->offer, (string) $offerLink->affiliate_id)) {
            return 'not_approved';
        }

        return null;
    }
}
