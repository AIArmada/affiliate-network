<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Queries;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use Carbon\CarbonImmutable;

final class ResolveOfferCouponQuery
{
    public function forSite(string $siteId, string $code, ?CarbonImmutable $at = null): ?AffiliateOfferCoupon
    {
        $at ??= CarbonImmutable::now();

        $coupon = AffiliateOfferCoupon::query()
            ->withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->where('code', mb_strtoupper(mb_trim($code)))
            ->where('is_active', true)
            ->first();

        if (! $coupon instanceof AffiliateOfferCoupon || ! $coupon->isActiveAt($at)) {
            return null;
        }

        return $coupon;
    }
}
