<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Exceptions\IneligibleOfferException;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Services\AffiliateOfferEligibility;
use AIArmada\AffiliateNetwork\Services\RedirectHostValidator;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CreateLink
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function execute(
        string $offerId,
        string $affiliateId,
        array $options = [],
    ): AffiliateOfferLink {
        $offer = AffiliateOffer::withoutGlobalScope('owner_via_site')
            ->whereKey($offerId)
            ->first();

        if ($offer === null) {
            throw (new ModelNotFoundException)->setModel(
                AffiliateOffer::class,
                [$offerId],
            );
        }

        $offer->loadMissing('site');

        if (! app(AffiliateOfferEligibility::class)->isEligible($offer, requirePublicVisibility: false)) {
            throw IneligibleOfferException::forOffer($offerId);
        }

        if (config('affiliates.owner.enabled', false)) {
            $affiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $affiliateId,
                includeGlobal: false,
                message: 'Affiliate is not accessible in the current owner scope.',
            );
        } else {
            $affiliate = Affiliate::query()->whereKey($affiliateId)->firstOrFail();
        }

        if ($offer->requires_approval) {
            $approved = AffiliateOfferApplication::query()
                ->where('offer_id', $offer->id)
                ->where('affiliate_id', $affiliate->id)
                ->where('status', ApplicationStatus::Approved)
                ->exists();

            if (! $approved) {
                throw IneligibleOfferException::forOffer($offerId);
            }
        }

        $targetUrl = $options['target_url']
            ?? $offer->landing_url
            ?? "https://{$offer->site->domain}/";

        app(RedirectHostValidator::class)->validate(
            targetUrl: $targetUrl,
            siteDomain: $offer->site->domain,
            siteAllowedHosts: $offer->site->allowed_redirect_hosts ?? [],
            offerAllowedHosts: $offer->restrictions['allowed_redirect_hosts'] ?? [],
        );

        return AffiliateOfferLink::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $affiliate->id,
            'site_id' => $offer->site_id,
            'target_url' => $targetUrl,
            'custom_parameters' => $options['custom_parameters'] ?? null,
            'sub_id' => $options['sub_id'] ?? null,
            'sub_id_2' => $options['sub_id_2'] ?? null,
            'sub_id_3' => $options['sub_id_3'] ?? null,
            'is_active' => $options['is_active'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }
}
