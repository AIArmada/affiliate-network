<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;

final class DeleteOffer
{
    public function execute(AffiliateOffer $offer): bool
    {
        return (bool) $offer->delete();
    }
}
