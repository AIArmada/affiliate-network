<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

/**
 * A ledger posting as seen by the network: identifiers and money.
 *
 * Status is the ledger's own status string (for the affiliates ledger,
 * the conversion status morph class).
 */
final readonly class NetworkPostedConversion
{
    public function __construct(
        public string $id,
        public string $affiliateCode,
        public int $commissionMinor,
        public string $commissionCurrency,
        public string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'affiliate_code' => $this->affiliateCode,
            'commission_minor' => $this->commissionMinor,
            'commission_currency' => $this->commissionCurrency,
            'status' => $this->status,
        ];
    }
}
