<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\FulfillmentReceipt;
use AIArmada\AffiliateNetwork\Data\FulfillmentResult;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

final class HostManualFulfillmentDriver implements Fulfillment
{
    public function fulfillsAutomatically(): bool
    {
        return false;
    }

    public function fulfill(NetworkLegSnapshot $leg, ?string $reference = null): FulfillmentResult
    {
        $existing = $leg->metadata['fulfilled'] ?? null;

        if (is_array($existing) && isset($existing['fulfilled_at'])) {
            return new FulfillmentResult(receipt: new FulfillmentReceipt(
                legId: $leg->id,
                adapter: (string) ($existing['adapter'] ?? 'host-manual'),
                reference: isset($existing['reference']) ? (string) $existing['reference'] : null,
                fulfilledAt: CarbonImmutable::parse((string) $existing['fulfilled_at']),
            ));
        }

        if ($leg->status !== LegStatus::Posted) {
            throw new RuntimeException('Only posted legs can be fulfilled.');
        }

        if (! is_string($reference) || mb_trim($reference) === '') {
            throw new InvalidArgumentException('Host fulfillment requires a non-empty payout reference.');
        }

        return new FulfillmentResult(receipt: new FulfillmentReceipt(
            legId: $leg->id,
            adapter: 'host-manual',
            reference: mb_trim($reference),
            fulfilledAt: CarbonImmutable::now(),
        ));
    }
}
