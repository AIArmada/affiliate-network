<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers;

use AIArmada\AffiliateNetwork\Contracts\DnsResolver;

final class NativeDnsDriver implements DnsResolver
{
    public function getTxtRecords(string $hostname): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($hostname, DNS_TXT);
        } finally {
            restore_error_handler();
        }

        if (! is_array($records)) {
            return [];
        }

        return $records;
    }
}
