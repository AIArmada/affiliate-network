<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

interface AffiliateReferenceReader
{
    public function existsFor(string $affiliateId, int $connectionInstanceId): bool;
}
