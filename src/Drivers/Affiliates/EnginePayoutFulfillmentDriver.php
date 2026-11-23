<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Data\FulfillmentResult;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use Carbon\CarbonImmutable;
use RuntimeException;

final class EnginePayoutFulfillmentDriver implements Fulfillment
{
    public function __construct(
        private readonly NetworkLedger $ledger,
    ) {}

    public function fulfillsAutomatically(): bool
    {
        return true;
    }

    public function fulfill(NetworkLegSnapshot $leg, ?string $reference = null): FulfillmentResult
    {
        if ($leg->status !== LegStatus::Posted) {
            throw new RuntimeException('Only posted legs can be fulfilled.');
        }

        $existing = $leg->metadata['fulfilled'] ?? null;

        if (is_array($existing) && isset($existing['fulfilled_at'])) {
            return new FulfillmentResult(receipt: new FulfillmentReceipt(
                legId: $leg->id,
                adapter: (string) ($existing['adapter'] ?? 'engine-payouts'),
                reference: isset($existing['reference']) ? (string) $existing['reference'] : null,
                fulfilledAt: CarbonImmutable::parse((string) $existing['fulfilled_at']),
            ));
        }

        $couponKey = $leg->couponCode ?? $leg->offerId;

        $posted = $this->ledger->post(new NetworkConversionDraft(
            linkId: $leg->linkId ?? 'coupon:' . $couponKey,
            offerId: $leg->offerId,
            siteId: $leg->siteId,
            affiliateId: $leg->affiliateId,
            linkCode: $leg->linkCode ?? 'coupon:' . ($leg->couponCode ?? ''),
            revenueMinor: $leg->revenueMinor,
            currency: $leg->commissionCurrency,
            externalReference: $leg->externalReference,
            commissionMinor: $leg->payoutMinor,
            metadata: [
                'gross_commission_minor' => $leg->commissionMinor,
                'network_fee_minor' => $leg->feeMinor,
                'network_fee_bp' => $leg->feeBp,
                'tier_rate_bp' => $leg->tierRateBp,
            ],
        ));

        if ($posted === null) {
            return new FulfillmentResult(unfulfillableReason: 'merchant_not_found');
        }

        if (self::isRejected($posted->status)) {
            return new FulfillmentResult(unfulfillableReason: 'merchant_rejected');
        }

        if ($posted->commissionMinor !== $leg->payoutMinor) {
            return new FulfillmentResult(unfulfillableReason: 'commission_mismatch');
        }

        if (mb_strtoupper($posted->commissionCurrency) !== mb_strtoupper($leg->commissionCurrency)) {
            return new FulfillmentResult(unfulfillableReason: 'currency_mismatch');
        }

        return new FulfillmentResult(receipt: new FulfillmentReceipt(
            legId: $leg->id,
            adapter: 'engine-payouts',
            reference: $posted->id,
            fulfilledAt: CarbonImmutable::now(),
        ));
    }

    private static function isRejected(string $status): bool
    {
        $normalized = mb_strtolower(basename(str_replace('\\', '/', $status)));

        return in_array($normalized, ['rejected', 'rejectedconversion'], true);
    }
}
