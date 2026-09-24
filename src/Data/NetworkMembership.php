<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

/**
 * A core-program membership as seen by the network.
 */
final readonly class NetworkMembership
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $programId,
        public string $status,
    ) {}

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
