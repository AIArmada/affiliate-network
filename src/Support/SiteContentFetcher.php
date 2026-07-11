<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SiteContentFetcher
{
    private readonly DnsRecordResolver $dns;

    public function __construct(?DnsRecordResolver $dns = null)
    {
        $this->dns = $dns ?? new DnsRecordResolver;
    }

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

            $pinEntries = $this->resolveAndPinHost($domain, $scheme);

            if ($pinEntries === null) {
                continue;
            }

            try {
                $request = Http::connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->retry($retries, $retrySleepMs, throw: false);

                if ($pinEntries !== []) {
                    $request = $request->withOptions([
                        'curl' => [
                            CURLOPT_RESOLVE => $pinEntries,
                        ],
                    ]);
                }

                $response = $request->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function resolveAndPinHost(string $hostname, string $scheme): ?array
    {
        if (config('affiliate-network.http.skip_dns_check', false)) {
            return [];
        }

        $ips = $this->resolveAllAddresses($hostname);

        if (empty($ips)) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return null;
            }
        }

        $port = $scheme === 'https' ? 443 : 80;

        return array_map(fn (string $ip): string => "{$hostname}:{$port}:{$ip}", $ips);
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

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $normalizedDomain) === 1;
    }

    private function resolveAllAddresses(string $hostname): array
    {
        $addresses = [];

        foreach ($this->dns->getRecords($hostname, DNS_A) as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }
        }

        foreach ($this->dns->getRecords($hostname, DNS_AAAA) as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
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
