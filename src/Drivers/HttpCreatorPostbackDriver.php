<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers;

use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Data\CreatorPostbackResponse;
use AIArmada\AffiliateNetwork\Platform\BoundedHttpResponseBody;
use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Throwable;

final class HttpCreatorPostbackDriver implements CreatorPostbackTransport
{
    public function __construct(
        private readonly PublicHttpUrlGuard $urlGuard = new PublicHttpUrlGuard,
        private readonly PinnedHttpClient $http = new PinnedHttpClient,
    ) {}

    public function send(string $method, string $url, array $headers = [], ?array $body = null): CreatorPostbackResponse
    {
        try {
            $target = $this->urlGuard->validate($url);
            $method = mb_strtoupper($method);
            $options = $body !== null && $method !== 'GET' ? ['json' => $body] : [];

            $response = $this->http->send(
                method: $method,
                target: $target,
                options: $options,
                headers: $headers,
                connectTimeout: max(1, (int) config('affiliate-network.http.connect_timeout_seconds', 3)),
                timeout: max(1, (int) config('affiliate-network.http.timeout_seconds', 5)),
                attempts: max(1, (int) config('affiliate-network.http.retries', 1)),
                retrySleepMilliseconds: max(0, (int) config('affiliate-network.http.retry_sleep_ms', 150)),
            );

            $bounded = BoundedHttpResponseBody::read(
                $response,
                (int) config('affiliate-network.http.max_response_bytes', 1024 * 1024),
            );

            if ($bounded === null) {
                return new CreatorPostbackResponse(false, $response->status(), 'response_too_large');
            }

            return new CreatorPostbackResponse(
                $response->successful(),
                $response->status(),
                $response->successful() ? null : 'http_' . $response->status(),
            );
        } catch (Throwable) {
            return new CreatorPostbackResponse(false, null, 'transport_failed');
        }
    }
}
