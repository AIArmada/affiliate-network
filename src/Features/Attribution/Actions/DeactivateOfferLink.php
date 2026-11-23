<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;

final class DeactivateOfferLink
{
    public function execute(string $linkId): void
    {
        AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereKey($linkId)
            ->update(['is_active' => false]);
    }
}
