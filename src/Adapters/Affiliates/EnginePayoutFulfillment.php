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

    public function fulfillsAutomatically(): bool
    {
        return true;
    }

    public function fulfill(NetworkConversionLeg $leg, ?string $reference = null): ?FulfillmentReceipt
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
            return $this->markUnfulfillable($leg, 'merchant_not_found');
        }

        if (self::isRejected($posted->status)) {
            return $this->markUnfulfillable($leg, 'merchant_rejected');
        }

        if ($posted->commissionMinor !== (int) $leg->payout_minor) {
            return $this->markUnfulfillable($leg, 'commission_mismatch');
        }

        if (mb_strtoupper($posted->commissionCurrency) !== mb_strtoupper((string) $leg->commission_currency)) {
            return $this->markUnfulfillable($leg, 'currency_mismatch');
        }

        $receipt = new FulfillmentReceipt(
            legId: (string) $leg->getKey(),
            adapter: 'engine-payouts',
            reference: $posted->id,
            fulfilledAt: CarbonImmutable::now(),
        );

        $metadata = $leg->metadata ?? [];
        unset($metadata['unfulfillable_reason'], $metadata['last_attempt_at']);
        $leg->update(['metadata' => array_merge($metadata, [
            'fulfilled' => $receipt->toArray(),
            'unfulfillable' => false,
            'reconciliation_required' => false,
        ])]);

        return $receipt;
    }

    private function markUnfulfillable(NetworkConversionLeg $leg, string $reason): null
    {
        $leg->update(['metadata' => array_merge($leg->metadata ?? [], [
            'unfulfillable' => true,
            'reconciliation_required' => true,
            'unfulfillable_reason' => $reason,
            'last_attempt_at' => CarbonImmutable::now()->toIso8601String(),
        ])]);

        return null;
    }

    private static function isRejected(string $status): bool
    {
        $normalized = mb_strtolower(basename(str_replace('\\', '/', $status)));

        return in_array($normalized, ['rejected', 'rejectedconversion'], true);
    }
}
