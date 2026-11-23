<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

final class DomainHostValidator
{
    public static function isValidHostname(string $host): bool
    {
        $host = mb_rtrim(mb_strtolower(mb_trim($host)), '.');

        if ($host === '' || mb_strlen($host) > 253) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            return false;
        }

        if (! str_contains($host, '.')) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    public static function isAllowedTargetUrl(string $url, string $verifiedDomain, bool $allowSubdomains = true): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(mb_strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = mb_rtrim(mb_strtolower($parts['host']), '.');
        $domain = mb_rtrim(mb_strtolower(mb_trim($verifiedDomain)), '.');

        if ($host === '' || $domain === '') {
            return false;
        }

        if ($host === $domain) {
            return true;
        }

        return $allowSubdomains && str_ends_with($host, '.' . $domain);
    }
}
