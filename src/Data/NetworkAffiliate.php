<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class NetworkAffiliate
{
    public function __construct(
        public string $id,
        public string $code,
        public string $handle,
        public ?string $email = null,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
        public ?string $userId = null,
    ) {}
}
