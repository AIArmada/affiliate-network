<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;

final class DeleteCategoryDependents
{
    public function execute(string $categoryId): void
    {
        $category = AffiliateOfferCategory::query()
            ->withoutGlobalScopes()
            ->whereKey($categoryId)
            ->first();

        if (! $category instanceof AffiliateOfferCategory) {
            return;
        }

        AffiliateOfferCategory::query()
            ->withoutGlobalScopes()
            ->where('parent_id', $categoryId)
            ->update(['parent_id' => $category->parent_id]);

        AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->where('category_id', $categoryId)
            ->update(['category_id' => null]);
    }
}
