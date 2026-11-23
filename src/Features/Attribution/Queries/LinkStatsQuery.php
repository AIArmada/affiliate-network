<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Queries;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;

final class LinkStatsQuery
{
    /**
     * @return array{clicks: int, conversions: int, revenue: int, currency: string|null, conversion_rate: float, revenue_per_click: float}
     */
    public function execute(string $linkId): array
    {
        $link = AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereKey($linkId)
            ->first();

        if (! $link instanceof AffiliateOfferLink) {
            return [
                'clicks' => 0,
                'conversions' => 0,
                'revenue' => 0,
                'currency' => null,
                'conversion_rate' => 0.0,
                'revenue_per_click' => 0.0,
            ];
        }

        $clicks = (int) $link->clicks;
        $conversions = (int) $link->conversions;
        $revenue = (int) $link->revenue;

        return [
            'clicks' => $clicks,
            'conversions' => $conversions,
            'revenue' => $revenue,
            'currency' => $link->currency,
            'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0.0,
            'revenue_per_click' => $clicks > 0 ? round($revenue / $clicks, 2) : 0.0,
        ];
    }
}
