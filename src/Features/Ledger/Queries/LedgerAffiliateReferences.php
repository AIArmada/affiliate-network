<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Queries;

use AIArmada\AffiliateNetwork\Contracts\AffiliateReferenceReader;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Platform\AffiliateReferenceCheck;

final readonly class LedgerAffiliateReferences implements AffiliateReferenceReader
{
    public function existsFor(string $affiliateId, int $connectionInstanceId): bool
    {
        return AffiliateReferenceCheck::exists(new NetworkConversionLeg, $affiliateId, $connectionInstanceId);
    }
}
