<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Queries;

use AIArmada\AffiliateNetwork\Contracts\AffiliateReferenceReader;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Platform\AffiliateReferenceCheck;

final readonly class PostbacksAffiliateReferences implements AffiliateReferenceReader
{
    public function existsFor(string $affiliateId, int $connectionInstanceId): bool
    {
        return AffiliateReferenceCheck::exists(new CreatorPostbackEndpoint, $affiliateId, $connectionInstanceId);
    }
}
