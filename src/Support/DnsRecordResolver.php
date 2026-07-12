<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

final class DnsRecordResolver
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getRecords(string $hostname, int $type): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($hostname, $type);
        } finally {
            restore_error_handler();
        }

        if (! is_array($records)) {
            return [];
        }

        return $records;
    }
}
