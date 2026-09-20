<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use Illuminate\Support\Facades\Log;

final class RecordNetworkConversion
{
    public function execute(AffiliateOfferLink $link, int $revenueMinor = 0, ?string $currency = null): void
    {
        if ($revenueMinor !== 0 && self::isCurrencyMismatch($link->currency, $currency)) {
            // A conversion happened, but its money is not in the link
            // currency: count it and skip revenue so totals never mix
            // currencies silently.
            Log::warning('affiliate-network.conversion.currency_mismatch', [
                'link_id' => $link->getKey(),
                'offer_id' => $link->offer_id,
                'link_currency' => $link->currency,
                'conversion_currency' => $currency,
                'revenue_minor' => $revenueMinor,
            ]);

            $revenueMinor = 0;
        }

        $link->recordConversion($revenueMinor);

        event(new NetworkConversionRecorded($link, $revenueMinor, $currency));
    }

    private static function isCurrencyMismatch(?string $linkCurrency, ?string $conversionCurrency): bool
    {
        if ($linkCurrency === null || $conversionCurrency === null) {
            return false;
        }

        return mb_strtoupper($linkCurrency) !== mb_strtoupper($conversionCurrency);
    }
}
