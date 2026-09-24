<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Merchant offer submission.
 *
 * Verified sites only, and always as a draft: publishing stays an explicit
 * operator decision (admin activate/publish), so unverified merchants can
 * never ride on the marketplace.
 */
final class SubmitOffer
{
    public function __construct(
        private readonly CreateOffer $createOffer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateSite $site, array $data): AffiliateOffer
    {
        if (! $site->isVerified()) {
            throw new AuthorizationException('Only verified sites can submit offers.');
        }

        $data['status'] = OfferStatus::Draft;

        return $this->createOffer->execute($site, $data);
    }
}
