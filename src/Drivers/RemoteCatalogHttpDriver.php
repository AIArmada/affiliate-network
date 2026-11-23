<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers;

use AIArmada\AffiliateNetwork\Contracts\CatalogReader;
use AIArmada\AffiliateNetwork\Data\SiteCatalogEndpoint;
use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Platform\BoundedHttpResponseBody;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
use Illuminate\Http\Client\Response;
use JsonException;
use Throwable;

final class RemoteCatalogHttpDriver implements CatalogReader
{
    public function __construct(
        private readonly PublicHttpUrlGuard $urlGuard = new PublicHttpUrlGuard,
        private readonly PinnedHttpClient $http = new PinnedHttpClient,
    ) {}

    public function snapshot(SiteCatalogEndpoint $site, string $programId): array
    {
        if ($site->catalogUrl === null || $site->catalogUrl === '') {
            throw new OfferNotFoundException('Site has no catalog_url for remote read.');
        }

        $base = mb_rtrim($site->catalogUrl, '/');
        $data = $this->get(
            $this->urlGuard->validate("{$base}/programs/" . rawurlencode($programId) . '/catalog'),
            $this->token($site),
            'Catalog',
        );

        if (($data['version'] ?? null) !== 'v2'
            || ($data['program_id'] ?? null) !== $programId
            || ! is_array($data['subjects'] ?? null)
            || ! is_array($data['creatives'] ?? null)) {
            throw new OfferNotFoundException('Invalid catalog payload.');
        }

        return $data;
    }

    public function programIds(SiteCatalogEndpoint $site): array
    {
        if ($site->catalogUrl === null || $site->catalogUrl === '') {
            throw new OfferNotFoundException('Site has no catalog_url for remote read.');
        }

        $base = mb_rtrim($site->catalogUrl, '/');
        $data = $this->get(
            $this->urlGuard->validate("{$base}/programs"),
            $this->token($site),
            'Program list',
        );

        $rows = $data['data'] ?? [];
        $rows = is_array($rows) ? array_values($rows) : [];

        $ids = [];

        foreach ($rows as $row) {
            if (is_array($row) && isset($row['program_id'])) {
                $ids[] = (string) $row['program_id'];
            }
        }

        return $ids;
    }

    private function token(SiteCatalogEndpoint $site): ?string
    {
        if ($site->catalogTokenEncrypted === null || $site->catalogTokenEncrypted === '') {
            return null;
        }

        try {
            $token = decrypt($site->catalogTokenEncrypted);
        } catch (Throwable) {
            throw new OfferNotFoundException('Catalog token cannot be decrypted.');
        }

        return is_string($token) ? $token : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(ValidatedHttpTarget $target, ?string $token, string $label): array
    {
        try {
            $response = $this->http->send(
                method: 'GET',
                target: $target,
                headers: array_filter([
                    'Accept' => 'application/json',
                    'Authorization' => $token !== null && $token !== '' ? 'Bearer ' . $token : null,
                ]),
                connectTimeout: max(1, (int) config('affiliate-network.http.connect_timeout_seconds', 3)),
                timeout: max(1, (int) config('affiliate-network.http.timeout_seconds', 5)),
                attempts: max(1, (int) config('affiliate-network.http.retries', 1)),
                retrySleepMilliseconds: max(0, (int) config('affiliate-network.http.retry_sleep_ms', 150)),
            );
        } catch (Throwable $exception) {
            throw new OfferNotFoundException(sprintf('%s fetch failed: %s', $label, $exception->getMessage()));
        }

        if (! $response->successful()) {
            throw new OfferNotFoundException(sprintf('%s fetch failed with status %d.', $label, $response->status()));
        }

        return $this->decodePayload($response, $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Response $response, string $label): array
    {
        $body = BoundedHttpResponseBody::read(
            $response,
            (int) config('affiliate-network.http.max_response_bytes', 1024 * 1024),
        );

        if ($body === null) {
            throw new OfferNotFoundException(sprintf('%s response exceeded the configured size limit.', $label));
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OfferNotFoundException(sprintf('Invalid %s payload.', mb_strtolower($label)));
        }

        if (! is_array($data)) {
            throw new OfferNotFoundException(sprintf('Invalid %s payload.', mb_strtolower($label)));
        }

        return $data;
    }
}
