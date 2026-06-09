<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SiteContentFetcher
{
    public function fetch(string $domain, string $path): ?string
    {
        if (! $this->isFetchableDomain($domain)) {
            return null;
        }

        $connectTimeout = (int) config('affiliate-network.http.connect_timeout_seconds', 3);
        $timeout = (int) config('affiliate-network.http.timeout_seconds', 5);
        $retries = (int) config('affiliate-network.http.retries', 1);
        $retrySleepMs = (int) config('affiliate-network.http.retry_sleep_ms', 150);

        foreach (['https', 'http'] as $scheme) {
            $url = sprintf('%s://%s%s', $scheme, $domain, $path);

            try {
                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->retry($retries, $retrySleepMs, throw: false)
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function isFetchableDomain(string $domain): bool
    {
        $normalizedDomain = Str::lower(mb_trim($domain));

        if ($normalizedDomain === '') {
            return false;
        }

        if (str_contains($normalizedDomain, '/') || str_contains($normalizedDomain, ':')) {
            return false;
        }

        if (in_array($normalizedDomain, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        if (str_ends_with($normalizedDomain, '.local') || str_ends_with($normalizedDomain, '.internal')) {
            return false;
        }

        if (filter_var($normalizedDomain, FILTER_VALIDATE_IP) !== false) {
            return filter_var($normalizedDomain, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $normalizedDomain) !== 1) {
            return false;
        }

        if (config('affiliate-network.http.skip_dns_check', false)) {
            return true;
        }

        $resolvedIps = $this->resolveAllAddresses($normalizedDomain);

        if (empty($resolvedIps)) {
            return false;
        }

        foreach ($resolvedIps as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function resolveAllAddresses(string $hostname): array
    {
        $addresses = [];

        $aRecords = @dns_get_record($hostname, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
            }
        }

        $aaaaRecords = @dns_get_record($hostname, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return $addresses;
    }

    private function isPublicIp(string $ip): bool
    {
        if (str_contains($ip, ':')) {
            if ($ip === '::1') {
                return false;
            }

            $packed = inet_pton($ip);

            if ($packed === false) {
                return false;
            }

            $firstByte = ord($packed[0]);
            if (($firstByte & 0xFE) === 0xFC) {
                return false;
            }

            if (($firstByte === 0xFE) && ((ord($packed[1]) & 0xC0) === 0x80)) {
                return false;
            }

            if (str_starts_with($packed, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xFF")) {
                $ipv4 = long2ip(unpack('N', mb_substr($packed, 12, 4))[1]);

                return $this->isPublicIp($ipv4);
            }

            return true;
        }

        $longIp = ip2long($ip);

        if ($longIp === false) {
            return false;
        }

        foreach ([
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['100.64.0.0', '100.127.255.255'],
        ] as [$start, $end]) {
            if ($longIp >= ip2long($start) && $longIp <= ip2long($end)) {
                return false;
            }
        }

        return true;
    }
}
