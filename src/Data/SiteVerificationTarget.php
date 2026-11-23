<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class SiteVerificationTarget
{
    public function __construct(
        public string $siteId,
        public string $domain,
        public ?string $verificationToken,
    ) {}
}
