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
 * Counter-only calls increment on every call. Booked calls increment only
 * when the reference creates a new leg; replays reuse the existing leg.
 * Fulfillment runs only for final (posted) legs and for adapters configured
 * to fulfill automatically.
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
        array $metadata = [],
    ): ?NetworkConversionLeg {
        $counterRevenue = $this->books->counterRevenue($link, $revenueMinor, $currency);

        $pricingCurrency = $link->currency ?? $link->offer?->currency;

        if ($counterRevenue !== $revenueMinor) {
            // A conversion happened, but its money is not in the link
            // currency: count it and skip revenue so totals never mix
            // currencies silently.
            Log::warning('affiliate-network.conversion.currency_mismatch', [
                'link_id' => $link->getKey(),
                'offer_id' => $link->offer_id,
                'pricing_currency' => $pricingCurrency,
                'conversion_currency' => $currency,
                'revenue_minor' => $revenueMinor,
            ]);

            $counterRevenue = 0;
        }

        if ($externalReference === null || $externalReference === '') {
            $link->recordConversion($counterRevenue);
            event(new NetworkConversionRecorded($link, $counterRevenue, $currency));

            return null;
        }

        $leg = $this->books->post($link, $revenueMinor, $currency, $externalReference, $status, $metadata);

        if ($leg->wasRecentlyCreated) {
            $link->recordConversion($counterRevenue);
            event(new NetworkConversionRecorded($link, $counterRevenue, $currency, $leg));
        }

        if ($leg->status === LegStatus::Posted && $this->fulfillment->fulfillsAutomatically()) {
            $this->fulfillment->fulfill($leg);
        }

        return $leg;
    }
}
