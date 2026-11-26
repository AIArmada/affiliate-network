<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Queries;

use AIArmada\AffiliateNetwork\Contracts\AffiliateReferenceReader;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Platform\AffiliateReferenceCheck;

final readonly class AttributionAffiliateReferences implements AffiliateReferenceReader
{
    public function existsFor(string $affiliateId, int $connectionInstanceId): bool
    {
        return AffiliateReferenceCheck::exists(new AffiliateOfferLink, $affiliateId, $connectionInstanceId)
            || AffiliateReferenceCheck::exists(new AffiliateOfferCoupon, $affiliateId, $connectionInstanceId);
    }
}
