<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Throwable;

final class SiteContentFetcher
{
    public function __construct(
        private readonly PublicHttpUrlGuard $urlGuard = new PublicHttpUrlGuard,
        private readonly PinnedHttpClient $http = new PinnedHttpClient,
    ) {}

    public function fetch(string $domain, string $path): ?string
    {
        $domain = mb_strtolower(mb_trim($domain));
        $path = '/' . mb_ltrim($path, '/');

        if ($domain === '') {
            return null;
        }

        foreach (['https', 'http'] as $scheme) {
            try {
                $target = $this->urlGuard->validate(sprintf('%s://%s%s', $scheme, $domain, $path));
                $response = $this->http->send(
                    method: 'GET',
                    target: $target,
                    headers: ['Accept' => 'text/html,application/xhtml+xml'],
                    connectTimeout: max(1, (int) config('affiliate-network.http.connect_timeout_seconds', 3)),
                    timeout: max(1, (int) config('affiliate-network.http.timeout_seconds', 5)),
                    attempts: max(1, (int) config('affiliate-network.http.retries', 1)),
                    retrySleepMilliseconds: max(0, (int) config('affiliate-network.http.retry_sleep_ms', 150)),
                );

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
