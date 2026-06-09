<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;

interface SiteVerificationStrategyInterface
{
    public function methodKey(): string;

    public function label(): string;

    public function verify(AffiliateSite $site): bool;

    public function getInstructions(AffiliateSite $site): array;
}
