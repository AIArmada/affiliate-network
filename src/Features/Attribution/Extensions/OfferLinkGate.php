<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Extensions;

use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\Links\Contracts\FallbackRedirectGateInterface;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class OfferLinkGate implements FallbackRedirectGateInterface, LinkGateInterface
{
    public function __construct(
        private readonly AttributionApi $attribution,
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly EnrollmentApi $enrollments,
        private readonly LedgerApi $ledger,
    ) {}

    public function blockedReason(Link $link): ?string
    {
        if ($link->subject_type !== (new AffiliateOfferLink)->getMorphClass()) {
            return null;
        }

        $offerLink = $this->attribution->findLinkByIdUnscoped((string) $link->subject_id);

        if (! $offerLink instanceof OfferLinkRecord) {
            return 'link_unknown';
        }

        if (! $offerLink->isActive) {
            return 'link_inactive';
        }

        $offer = $this->catalog->findOfferUnscoped($offerLink->offerId);

        if (! $offer instanceof OfferRecord) {
            return 'offer_inactive';
        }

        if (! $offer->isActiveAt(CarbonImmutable::now())) {
            return 'offer_inactive';
        }

        if (! $this->sites->isVerified($offer->siteId)) {
            return 'site_unverified';
        }

        if ($offer->requiresApproval && ! $this->enrollments->isApprovedForOfferUnscoped($offer->id, $offerLink->affiliateId)) {
            return 'not_approved';
        }

        if ($this->ledger->isOfferCapped($offer->id, $offerLink->affiliateId, 0) !== null) {
            return 'offer_capped';
        }

        return $this->catalog->evaluateRestrictions($offer, $this->restrictionContext());
    }

    public function fallbackUrl(Link $link, string $reason): ?string
    {
        if ($link->subject_type !== (new AffiliateOfferLink)->getMorphClass()) {
            return null;
        }

        $offerLink = $this->attribution->findLinkByIdUnscoped((string) $link->subject_id);

        if (! $offerLink instanceof OfferLinkRecord) {
            return null;
        }

        if (in_array($reason, ['link_unknown', 'site_unverified'], true)) {
            return null;
        }

        $offer = $this->catalog->findOfferUnscoped($offerLink->offerId);

        if (! $offer instanceof OfferRecord || ! $this->sites->isVerified($offer->siteId)) {
            return null;
        }

        return $this->catalog->fallbackUrlForOffer($offerLink->offerId);
    }

    private function restrictionContext(): RestrictionContext
    {
        $country = null;
        $device = null;

        if (app()->bound('request')) {
            $request = request();

            if ($request instanceof Request) {
                $country = $request->headers->get('CF-IPCountry')
                    ?? $request->headers->get('X-Country-Code');
                $device = self::deviceFromUserAgent((string) $request->userAgent());
            }
        }

        return new RestrictionContext(
            country: is_string($country) && $country !== '' ? mb_strtoupper($country) : null,
            device: $device,
        );
    }

    private static function deviceFromUserAgent(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $userAgent) === 1) {
            return 'tablet';
        }

        if (preg_match('/Mobile|Android|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini/i', $userAgent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }
}
