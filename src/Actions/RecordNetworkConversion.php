<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use Illuminate\Support\Facades\Log;

/**
 * Record one network conversion: cached counters, a book leg, fulfillment.
 *
 * Counters increment on every call (reporting caches). The book leg needs
 * an external reference for idempotency — calls without one keep
 * counters only. Fulfillment runs only for final (posted) legs;
 * provisional legs fulfill when the attribution finalizer confirms them.
 */
final class RecordNetworkConversion
{
    public function __construct(
        private readonly NetworkBooks $books,
        private readonly Fulfillment $fulfillment,
    ) {}

    public function execute(
        AffiliateOfferLink $link,
        int $revenueMinor = 0,
        ?string $currency = null,
        ?string $externalReference = null,
        LegStatus $status = LegStatus::Posted,
    ): ?NetworkConversionLeg {
        $counterRevenue = $revenueMinor;

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

            $counterRevenue = 0;
        }

        $link->recordConversion($counterRevenue);

        if ($externalReference === null || $externalReference === '') {
            event(new NetworkConversionRecorded($link, $counterRevenue, $currency));

            return null;
        }

        $leg = $this->books->post($link, $revenueMinor, $currency, $externalReference, $status);

        event(new NetworkConversionRecorded($link, $counterRevenue, $currency, $leg));

        if ($leg->status === LegStatus::Posted) {
            $this->fulfillment->fulfill($leg);
        }

        return $leg;
    }

    private static function isCurrencyMismatch(?string $linkCurrency, ?string $conversionCurrency): bool
    {
        if ($linkCurrency === null || $conversionCurrency === null) {
            return false;
        }

        return mb_strtoupper($linkCurrency) !== mb_strtoupper($conversionCurrency);
    }
}
