<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Exceptions\SiteVerificationFailedException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

final class VerifySite
{
    public function __construct(
        private readonly SiteVerificationService $service,
    ) {}

    public function execute(AffiliateSite $site, string $method): AffiliateSite
    {
        $verified = $this->service->verify($site, $method);

        if (! $verified) {
            throw SiteVerificationFailedException::methodFailed($method, 'Strategy did not confirm site ownership.');
        }

        return $site->fresh();
    }
}
