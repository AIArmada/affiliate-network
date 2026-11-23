<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;

final class RevokeOfferCoupon
{
    public function execute(string $couponId): void
    {
        AffiliateOfferCoupon::query()
            ->withoutGlobalScopes()
            ->whereKey($couponId)
            ->update(['is_active' => false]);
    }
}
