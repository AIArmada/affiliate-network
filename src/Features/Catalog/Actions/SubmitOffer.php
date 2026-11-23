<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use Illuminate\Auth\Access\AuthorizationException;

final class SubmitOffer
{
    public function __construct(
        private readonly CreateOffer $createOffer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SiteRecord $site, array $data): AffiliateOffer
    {
        if (! $site->isVerified()) {
            throw new AuthorizationException('Only verified sites can submit offers.');
        }

        $data['status'] = OfferStatus::Draft;

        return $this->createOffer->execute($site, $data);
    }
}
