<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

use Carbon\CarbonImmutable;

/**
 * Proof that a creator leg was fulfilled, by whichever adapter paid it.
 */
final readonly class FulfillmentReceipt
{
    public function __construct(
        public string $legId,
        public string $adapter,
        public ?string $reference,
        public CarbonImmutable $fulfilledAt,
    ) {}

    /**
     * @return array{leg_id: string, adapter: string, reference: string|null, fulfilled_at: string}
     */
    public function toArray(): array
    {
        return [
            'leg_id' => $this->legId,
            'adapter' => $this->adapter,
            'reference' => $this->reference,
            'fulfilled_at' => $this->fulfilledAt->toIso8601String(),
        ];
    }
}
