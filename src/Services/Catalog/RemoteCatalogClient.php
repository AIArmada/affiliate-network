<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services\Catalog;

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Support\BoundedHttpResponseBody;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Http\Client\Response;
use JsonException;
use Throwable;

/**
 * Remote reader for unowned sites. The site owner installs affiliates,
 * sets the API token, and configures catalog_url on the network site.
 */
final class RemoteCatalogClient implements CatalogReaderInterface
{
    public function __construct(
        private readonly PublicHttpUrlGuard $urlGuard = new PublicHttpUrlGuard,
        private readonly PinnedHttpClient $http = new PinnedHttpClient,
    ) {}

    public function snapshot(AffiliateSite $site, string $programId): array
    {
        if (empty($site->catalog_url)) {
            throw new OfferNotFoundException('Site has no catalog_url for remote read.');
        }

        $base = mb_rtrim($site->catalog_url, '/');
        $target = $this->urlGuard->validate("{$base}/programs/{$programId}/catalog");

        try {
            $response = $this->http->send(
                method: 'GET',
                target: $target,
                options: ['stream' => true],
                headers: array_filter([
                    'Accept' => 'application/json',
                    'Authorization' => $this->token($site) ? 'Bearer ' . $this->token($site) : null,
                ]),
                connectTimeout: max(1, (int) config('affiliate-network.http.connect_timeout_seconds', 3)),
                timeout: max(1, (int) config('affiliate-network.http.timeout_seconds', 5)),
                attempts: max(1, (int) config('affiliate-network.http.retries', 1)),
                retrySleepMilliseconds: max(0, (int) config('affiliate-network.http.retry_sleep_ms', 150)),
            );
        } catch (Throwable $e) {
            throw new OfferNotFoundException('Catalog fetch failed: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new OfferNotFoundException('Catalog fetch failed with status ' . $response->status());
        }

        $data = $this->decodePayload($response, 'Catalog');

        if (! isset($data['program_id'], $data['subjects']) || ! is_array($data['subjects'])) {
            throw new OfferNotFoundException('Invalid catalog payload.');
        }

        return $data;
    }

    public function programIds(AffiliateSite $site): array
    {
        if (empty($site->catalog_url)) {
            throw new OfferNotFoundException('Site has no catalog_url for remote read.');
        }

        $base = mb_rtrim($site->catalog_url, '/');
        $target = $this->urlGuard->validate("{$base}/programs");

        try {
            $response = $this->http->send(
                method: 'GET',
                target: $target,
                options: ['stream' => true],
                headers: array_filter([
                    'Accept' => 'application/json',
                    'Authorization' => $this->token($site) ? 'Bearer ' . $this->token($site) : null,
                ]),
                connectTimeout: max(1, (int) config('affiliate-network.http.connect_timeout_seconds', 3)),
                timeout: max(1, (int) config('affiliate-network.http.timeout_seconds', 5)),
                attempts: max(1, (int) config('affiliate-network.http.retries', 1)),
                retrySleepMilliseconds: max(0, (int) config('affiliate-network.http.retry_sleep_ms', 150)),
            );
        } catch (Throwable $e) {
            throw new OfferNotFoundException('Program list fetch failed: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new OfferNotFoundException('Program list fetch failed with status ' . $response->status());
        }

        $data = $this->decodePayload($response, 'Program list');

        $rows = $data['data'] ?? [];
        $rows = is_array($rows) ? array_values($rows) : [];

        return collect($rows)
            ->map(fn (mixed $row): ?string => is_array($row) && isset($row['program_id']) ? (string) $row['program_id'] : null)
            ->filter()
            ->values()
            ->all();
    }

    private function token(AffiliateSite $site): ?string
    {
        if (empty($site->catalog_token_encrypted)) {
            return null;
        }

        try {
            return decrypt($site->catalog_token_encrypted);
        } catch (Throwable) {
            return null;
        }
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
            /** @var mixed $data */
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
