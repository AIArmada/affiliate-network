<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Adapters\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Creator-leg fulfillment through merchant payouts.
 *
 * Posts the leg's creator share into merchant books; the merchant's
 * normal payout flow pays it from there. Only posted legs fulfill —
 * provisional, superseded, and reversed legs never reach the ledger.
 */
final class EnginePayoutFulfillment implements Fulfillment
{
    public function __construct(
        private readonly NetworkLedger $ledger,
    ) {}

    public function fulfill(NetworkConversionLeg $leg, ?string $reference = null): FulfillmentReceipt
    {
        if ($leg->status !== LegStatus::Posted) {
            throw new RuntimeException('Only posted legs can be fulfilled.');
        }

        $existing = $leg->metadata['fulfilled'] ?? null;

        if (is_array($existing) && isset($existing['fulfilled_at'])) {
            return new FulfillmentReceipt(
                legId: (string) $leg->getKey(),
                adapter: (string) ($existing['adapter'] ?? 'engine-payouts'),
                reference: isset($existing['reference']) ? (string) $existing['reference'] : null,
                fulfilledAt: CarbonImmutable::parse((string) $existing['fulfilled_at']),
            );
        }

        $posted = $this->ledger->post(new NetworkConversionDraft(
            linkId: (string) $leg->link_id,
            offerId: (string) $leg->offer_id,
            siteId: $leg->site_id !== null ? (string) $leg->site_id : null,
            affiliateId: (string) $leg->affiliate_id,
            linkCode: (string) $leg->link_code,
            revenueMinor: (int) $leg->revenue_minor,
            currency: (string) $leg->commission_currency,
            externalReference: (string) $leg->external_reference,
            commissionMinor: (int) $leg->payout_minor,
            metadata: [
                'gross_commission_minor' => (int) $leg->commission_minor,
                'network_fee_minor' => (int) $leg->fee_minor,
                'network_fee_bp' => (int) $leg->fee_bp,
                'tier_rate_bp' => $leg->tier_rate_bp,
            ],
        ));

        if ($posted === null) {
            $leg->update(['metadata' => array_merge($leg->metadata ?? [], [
                'unfulfillable' => true,
                'last_attempt_at' => CarbonImmutable::now()->toIso8601String(),
            ])]);

            return new FulfillmentReceipt(
                legId: (string) $leg->getKey(),
                adapter: 'engine-payouts',
                reference: $reference,
                fulfilledAt: CarbonImmutable::now(),
            );
        }

        $receipt = new FulfillmentReceipt(
            legId: (string) $leg->getKey(),
            adapter: 'engine-payouts',
            reference: $posted->id,
            fulfilledAt: CarbonImmutable::now(),
        );

        $leg->update(['metadata' => array_merge($leg->metadata ?? [], [
            'fulfilled' => $receipt->toArray(),
            'unfulfillable' => false,
        ])]);

        return $receipt;
    }
}
