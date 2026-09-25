<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Carbon\CarbonImmutable;

/**
 * Host-recorded fulfillment for engine-less installs.
 *
 * Records the host's payout run against the leg (bank transfer, rails
 * payout, manual run) without moving money itself. Idempotent: a leg
 * fulfilled once returns its original receipt.
 */
final class HostManualFulfillment implements Fulfillment
{
    public function fulfill(NetworkConversionLeg $leg, ?string $reference = null): FulfillmentReceipt
    {
        $existing = $leg->metadata['fulfilled'] ?? null;

        if (is_array($existing) && isset($existing['fulfilled_at'])) {
            return new FulfillmentReceipt(
                legId: (string) $leg->getKey(),
                adapter: (string) ($existing['adapter'] ?? 'host-manual'),
                reference: isset($existing['reference']) ? (string) $existing['reference'] : null,
                fulfilledAt: CarbonImmutable::parse((string) $existing['fulfilled_at']),
            );
        }

        $receipt = new FulfillmentReceipt(
            legId: (string) $leg->getKey(),
            adapter: 'host-manual',
            reference: $reference,
            fulfilledAt: CarbonImmutable::now(),
        );

        $leg->update(['metadata' => array_merge($leg->metadata ?? [], ['fulfilled' => $receipt->toArray()])]);

        return $receipt;
    }
}
