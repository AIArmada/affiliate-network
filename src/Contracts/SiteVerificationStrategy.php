<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\SiteVerificationTarget;

interface SiteVerificationStrategy
{
    public function methodKey(): string;

    public function label(): string;

    public function verify(SiteVerificationTarget $target): bool;

    /**
     * @return array<string, mixed>
     */
    public function getInstructions(SiteVerificationTarget $target): array;
}
