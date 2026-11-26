<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Queries;

use AIArmada\AffiliateNetwork\Contracts\AffiliateReferenceReader;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Platform\AffiliateReferenceCheck;

final readonly class EnrollmentAffiliateReferences implements AffiliateReferenceReader
{
    public function existsFor(string $affiliateId, int $connectionInstanceId): bool
    {
        return AffiliateReferenceCheck::exists(new AffiliateOfferApplication, $affiliateId, $connectionInstanceId)
            || AffiliateReferenceCheck::exists(new AffiliateOfferInvitation, $affiliateId, $connectionInstanceId)
            || AffiliateReferenceCheck::exists(new AffiliateOfferDeal, $affiliateId, $connectionInstanceId);
    }
}
