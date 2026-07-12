<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

final class SiteContentFetcher
{
    private readonly PublicHttpUrlGuard $urlGuard;

    public function __construct(?PublicHttpUrlGuard $urlGuard = null)
    {
        $this->urlGuard = $urlGuard ?? new PublicHttpUrlGuard;
    }

    public function fetch(string $domain, string $path): ?string
    {
        $domain = strtolower(trim($domain));
        $path = '/' . ltrim($path, '/');

        if ($domain === '') {
            return null;
        }

        $connectTimeout = (int) config('affiliate-network.http.connect_timeout_seconds', 3);
        $timeout = (int) config('affiliate-network.http.timeout_seconds', 5);
        $retries = (int) config('affiliate-network.http.retries', 1);
        $retrySleepMs = (int) config('affiliate-network.http.retry_sleep_ms', 150);

        foreach (['https', 'http'] as $scheme) {
            $url = sprintf('%s://%s%s', $scheme, $domain, $path);

            if (! $this->urlGuard->isAllowed($url)) {
                continue;
            }

            try {
                $response = Http::withoutRedirecting()
                    ->connectTimeout($connectTimeout)
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
}
