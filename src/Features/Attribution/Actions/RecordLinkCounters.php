<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;

final class RecordLinkCounters
{
    public function execute(string $linkId, int $revenueDeltaMinor, int $conversionDelta = 1): void
    {
        $link = AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereKey($linkId)
            ->first();

        if (! $link instanceof AffiliateOfferLink) {
            return;
        }

        $clicks = (int) $link->clicks;
        $conversions = max(0, (int) $link->conversions + $conversionDelta);
        $revenue = max(0, (int) $link->revenue + $revenueDeltaMinor);

        $link->forceFill([
            'clicks' => $clicks,
            'conversions' => $conversions,
            'revenue' => $revenue,
        ])->save();
    }
}
