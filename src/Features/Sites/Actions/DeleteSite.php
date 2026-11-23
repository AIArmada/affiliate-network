<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;

final class DeleteSite
{
    public function execute(AffiliateSite $site): bool
    {
        return (bool) $site->delete();
    }
}
