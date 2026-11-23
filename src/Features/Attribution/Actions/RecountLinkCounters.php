<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;

final class RecountLinkCounters
{
    public function execute(string $linkId, int $conversions, int $revenueMinor): void
    {
        AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereKey($linkId)
            ->update([
                'conversions' => max(0, $conversions),
                'revenue' => max(0, $revenueMinor),
            ]);
    }
}
