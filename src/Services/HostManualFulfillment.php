<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Host-recorded fulfillment for engine-less installs.
 *
 * Records the host's payout run against the leg (bank transfer, rails
 * payout, manual run) without moving money itself. Idempotent: a leg
 * fulfilled once returns its original receipt.
 */
final class HostManualFulfillment implements Fulfillment
{
    public function fulfillsAutomatically(): bool
    {
        return false;
    }

    public function fulfill(NetworkConversionLeg $leg, ?string $reference = null): FulfillmentReceipt
    {
        $existing = $leg->metadata['fulfilled'] ?? null;

        if (is_array($existing) && isset($existing['fulfilled_at'])) {
            return new FulfillmentReceipt(
                legId: (string) $leg->getKey(),
                adapter: (string) $existing['adapter'],
                reference: (string) $existing['reference'],
                fulfilledAt: CarbonImmutable::parse((string) $existing['fulfilled_at']),
            );
        }

        if ($leg->status !== LegStatus::Posted) {
            throw new RuntimeException('Only posted legs can be fulfilled.');
        }

        if (! is_string($reference) || mb_trim($reference) === '') {
            throw new InvalidArgumentException('Host fulfillment requires a non-empty payout reference.');
        }

        $receipt = new FulfillmentReceipt(
            legId: (string) $leg->getKey(),
            adapter: 'host-manual',
            reference: mb_trim($reference),
            fulfilledAt: CarbonImmutable::now(),
        );

        $leg->update(['metadata' => array_merge($leg->metadata ?? [], ['fulfilled' => $receipt->toArray()])]);

        return $receipt;
    }
}
