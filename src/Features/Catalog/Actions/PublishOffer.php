<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;

final class PublishOffer
{
    public function __construct(
        private readonly UpdateOffer $updateOffer,
    ) {}

    public function execute(AffiliateOffer $offer): AffiliateOffer
    {
        return $this->updateOffer->execute($offer, [
            'status' => OfferStatus::Published,
        ]);
    }
}
