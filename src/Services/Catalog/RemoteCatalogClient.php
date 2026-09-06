<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services\Catalog;

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
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

        /** @var array<string, mixed> $data */
        $data = $response->json();

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

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return collect($data['data'] ?? [])
            ->map(fn ($row): ?string => isset($row['program_id']) ? (string) $row['program_id'] : null)
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
}
