<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

interface DnsResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTxtRecords(string $hostname): array;
}
