<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;

final class DeleteCategory
{
    public function execute(AffiliateOfferCategory $category): bool
    {
        return (bool) $category->delete();
    }
}
