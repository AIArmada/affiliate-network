<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;

final class MarkSiteVerified
{
    /**
     * Privileged override: marks a site as verified without running
     * the verification strategy. Use only when the operator has
     * independently confirmed site ownership.
     */
    public function execute(AffiliateSite $site, ?string $method = null): AffiliateSite
    {
        $site->update([
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verification_method' => $method,
            'verified_at' => CarbonImmutable::now(),
        ]);

        return $site->fresh();
    }
}
