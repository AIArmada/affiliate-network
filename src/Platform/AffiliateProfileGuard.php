<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Exceptions\AffiliateProfileRequired;

final readonly class AffiliateProfileGuard
{
    public function __construct(
        private AffiliateIdentityResolver $identities,
    ) {}

    public function assertCanWriteFor(string $affiliateId): void
    {
        if (
            (! config('affiliate-network.owner.enabled', false)
                && ! config('affiliates.owner.enabled', false))
            || ! $this->identities->requiresMerchantProfile()
            || $this->identities->hasMerchantProfile($affiliateId)
        ) {
            return;
        }

        throw new AffiliateProfileRequired;
    }
}
