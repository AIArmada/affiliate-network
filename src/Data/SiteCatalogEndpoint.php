<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class SiteCatalogEndpoint
{
    public function __construct(
        public string $siteId,
        public string $domain,
        public ?string $catalogUrl,
        public ?string $catalogTokenEncrypted,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}
}
