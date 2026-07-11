<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Exceptions\UnauthorizedRedirectTargetException;

final class RedirectHostValidator
{
    /**
     * @param  list<string>  $siteAllowedHosts
     * @param  list<string>  $offerAllowedHosts
     */
    public function validate(
        string $targetUrl,
        string $siteDomain,
        array $siteAllowedHosts = [],
        array $offerAllowedHosts = [],
    ): void {
        $targetUrl = mb_trim($targetUrl);

        $scheme = mb_strtolower((string) parse_url($targetUrl, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnauthorizedRedirectTargetException('Only http and https redirect targets are allowed.');
        }

        $host = $this->normalizeHost(parse_url($targetUrl, PHP_URL_HOST));

        if ($host === '') {
            throw new UnauthorizedRedirectTargetException('Redirect target has no host.');
        }

        $normalizedSiteDomain = $this->normalizeHost($siteDomain);

        if ($host === $normalizedSiteDomain) {
            return;
        }

        if (str_ends_with($host, '.' . $normalizedSiteDomain)) {
            return;
        }

        foreach ($siteAllowedHosts as $allowed) {
            if ($host === $this->normalizeHost($allowed)) {
                return;
            }
        }

        foreach ($offerAllowedHosts as $allowed) {
            if ($host === $this->normalizeHost($allowed)) {
                return;
            }
        }

        throw UnauthorizedRedirectTargetException::forHost($host);
    }

    private function normalizeHost(?string $host): string
    {
        if ($host === null || $host === '') {
            return '';
        }

        $host = rawurldecode($host);

        $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return mb_strtolower($ascii !== false ? $ascii : $host);
    }
}
