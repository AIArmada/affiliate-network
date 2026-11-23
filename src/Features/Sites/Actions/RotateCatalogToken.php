<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;

final class RotateCatalogToken
{
    public function __construct(
        private readonly IssueCatalogToken $issueCatalogToken,
    ) {}

    public function execute(AffiliateSite $site): string
    {
        return $this->issueCatalogToken->execute($site);
    }
}
