<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;

/**
 * Post one network conversion to the ledger.
 *
 * Network links only hold counters; the ledger is the system of record for
 * balances and payouts. Commission math and currency resolution are owned
 * here (offer rates are network data); persistence, idempotency, fraud,
 * and accounting belong to the ledger adapter. Without a ledger — or an
 * external reference to dedupe on — the caller keeps counters only.
 */
final class PostNetworkConversionToLedger
{
    public function __construct(
        private readonly ?NetworkLedger $ledger = null,
    ) {}

    public function execute(
        AffiliateOfferLink $link,
        int $revenueMinor,
        ?string $currency,
        string $externalReference,
    ): ?NetworkPostedConversion {
        if ($this->ledger === null) {
            return null;
        }

        $offer = $link->relationLoaded('offer') ? $link->offer : $link->offer()->first();

        if ($offer === null || ($offer->rate_fixed_minor === null && $offer->rate_base_bp === null)) {
            return null;
        }

        $resolvedCurrency = mb_strtoupper((string) ($currency
            ?? ($link->currency ?? null)
            ?? ($offer->currency ?? null)
            ?? config('affiliate-network.currency.default', 'MYR')));

        $commission = $offer->rate_fixed_minor !== null
            ? (int) $offer->rate_fixed_minor
            : (int) round(max(0, $revenueMinor) * (int) $offer->rate_base_bp / 10000);

        return $this->ledger->post(new NetworkConversionDraft(
            linkId: (string) $link->getKey(),
            offerId: (string) $link->offer_id,
            siteId: $link->site_id !== null ? (string) $link->site_id : null,
            affiliateId: (string) $link->affiliate_id,
            linkCode: (string) $link->code,
            revenueMinor: max(0, $revenueMinor),
            currency: $resolvedCurrency,
            externalReference: $externalReference,
            commissionMinor: $commission,
        ));
    }
}
