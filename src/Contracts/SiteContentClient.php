<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

interface SiteContentClient
{
    public function fetch(string $domain, string $path): ?string;
}
