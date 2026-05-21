<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SiteVerificationService
{
    /**
     * Generate a verification token for a site.
     */
    public function generateToken(AffiliateSite $site): string
    {
        $token = 'affiliatenetwork-verify-' . Str::random(32);

        $site->update([
            'verification_token' => $token,
        ]);

        return $token;
    }

    /**
     * Verify a site using the specified method.
     */
    public function verify(AffiliateSite $site, string $method): bool
    {
        if ($site->verification_token === null) {
            return false;
        }

        $verified = match ($method) {
            'dns' => $this->verifyDns($site),
            'meta_tag' => $this->verifyMetaTag($site),
            'file' => $this->verifyFile($site),
            default => false,
        };

        if ($verified) {
            $site->update([
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verification_method' => $method,
                'verified_at' => CarbonImmutable::now(),
            ]);
        }

        return $verified;
    }

    /**
     * Verify via DNS TXT record.
     */
    private function verifyDns(AffiliateSite $site): bool
    {
        $records = @dns_get_record($site->domain, DNS_TXT);

        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && $record['txt'] === $site->verification_token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify via meta tag on the homepage.
     */
    private function verifyMetaTag(AffiliateSite $site): bool
    {
        $html = $this->fetchRemoteContent($site, '/');

        if ($html === null) {
            return false;
        }

        // Require the token to appear within a <meta name="affiliate-network-verify" ...> tag,
        // not just anywhere in the page, to prevent coincidental or injected matches.
        $token = preg_quote($site->verification_token ?? '', '/');

        return preg_match(
            '/<meta[^>]+name=["\']affiliate-network-verify["\'][^>]+content=["\']' . $token . '["\'][^>]*>/i',
            $html,
        ) === 1 || preg_match(
            '/<meta[^>]+content=["\']' . $token . '["\'][^>]+name=["\']affiliate-network-verify["\'][^>]*>/i',
            $html,
        ) === 1;
    }

    /**
     * Verify via file at well-known path.
     */
    private function verifyFile(AffiliateSite $site): bool
    {
        $content = $this->fetchRemoteContent($site, '/.well-known/affiliate-network-verify.txt');

        if ($content === null) {
            return false;
        }

        return mb_trim($content) === $site->verification_token;
    }

    private function fetchRemoteContent(AffiliateSite $site, string $path): ?string
    {
        if (! $this->isFetchableDomain($site->domain)) {
            return null;
        }

        $connectTimeout = (int) config('affiliate-network.http.connect_timeout_seconds', 3);
        $timeout = (int) config('affiliate-network.http.timeout_seconds', 5);
        $retries = (int) config('affiliate-network.http.retries', 1);
        $retrySleepMs = (int) config('affiliate-network.http.retry_sleep_ms', 150);

        foreach (['https', 'http'] as $scheme) {
            $url = sprintf('%s://%s%s', $scheme, $site->domain, $path);

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

        // Resolve all DNS records (both A and AAAA) and block private/reserved address ranges (SSRF protection).
        // NOTE: there is an inherent TOCTOU gap between this check and the actual HTTP request (the HTTP client
        // performs its own DNS lookup). This is a known limitation; rate limiting verification requests reduces
        // exploitability of a DNS-rebinding attack.
        $resolvedIps = $this->resolveAllAddresses($normalizedDomain);

        // If DNS failed to resolve to any address, deny — do not allow the HTTP client to resolve independently
        // (which could reach IPv6-only internal services our gethostbyname check would silently miss).
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

    /**
     * Resolve a hostname to all its A and AAAA addresses.
     *
     * @return list<string>
     */
    private function resolveAllAddresses(string $hostname): array
    {
        $addresses = [];

        // IPv4 (A records)
        $aRecords = @dns_get_record($hostname, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
            }
        }

        // IPv6 (AAAA records)
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
        // IPv6 checks
        if (str_contains($ip, ':')) {
            if ($ip === '::1') {
                return false; // loopback
            }

            $packed = inet_pton($ip);

            if ($packed === false) {
                return false;
            }

            // fc00::/7 — unique local (private)
            $firstByte = ord($packed[0]);
            if (($firstByte & 0xFE) === 0xFC) {
                return false;
            }

            // fe80::/10 — link-local
            if (($firstByte === 0xFE) && ((ord($packed[1]) & 0xC0) === 0x80)) {
                return false;
            }

            // ::ffff:0:0/96 — IPv4-mapped; delegate to IPv4 check
            if (str_starts_with($packed, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xFF")) {
                $ipv4 = long2ip(unpack('N', mb_substr($packed, 12, 4))[1]);

                return $this->isPublicIp($ipv4);
            }

            return true;
        }

        // IPv4 checks
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

    /**
     * Get instructions for verification.
     *
     * @return array<string, string>
     */
    public function getInstructions(AffiliateSite $site, string $method): array
    {
        $token = $site->verification_token ?? $this->generateToken($site);

        return match ($method) {
            'dns' => [
                'title' => 'DNS TXT Record',
                'description' => "Add a TXT record to your domain's DNS settings.",
                'record_type' => 'TXT',
                'record_name' => '@',
                'record_value' => $token,
            ],
            'meta_tag' => [
                'title' => 'HTML Meta Tag',
                'description' => 'Add this meta tag to the <head> section of your homepage.',
                'html' => "<meta name=\"affiliate-network-verify\" content=\"{$token}\">",
            ],
            'file' => [
                'title' => 'Verification File',
                'description' => 'Create a file at the following path with the token as contents.',
                'path' => '/.well-known/affiliate-network-verify.txt',
                'content' => $token,
            ],
            default => [],
        };
    }
}
