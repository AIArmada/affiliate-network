<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;

final class InitiateSiteVerification
{
    public function __construct(
        private readonly SiteVerificationService $service,
    ) {}

    public function execute(AffiliateSite $site, string $method): array
    {
        $token = $this->service->generateToken($site);

        $instructions = $this->service->getInstructions($site, $method);

        return [
            'token' => $token,
            'method' => $method,
            'instructions' => $instructions,
        ];
    }
}
