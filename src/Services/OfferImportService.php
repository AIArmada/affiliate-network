<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderInterface;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Mirrors a merchant program catalog as network offers.
 *
 * Local (shared DB) when site has no catalog_url, remote HTTP otherwise.
 * Upserts by (site_id, external_program_id, subject_key); skips unchanged
 * checksums. Manual offers (external_program_id null) are never touched.
 */
final class OfferImportService
{
    public function __construct(
        private readonly CatalogReaderResolver $readers,
        private readonly CreateOffer $createOffer,
        private readonly UpdateOffer $updateOffer,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, locked: int, failed: int}
     */
    public function sync(AffiliateSite $site, string $programId): array
    {
        $snapshot = $this->readerFor($site)->snapshot($site, $programId);
        $source = empty($site->catalog_url) ? 'local' : 'remote';

        $created = $updated = $skipped = $locked = $failed = 0;
        $maxSubjects = max(1, (int) config('affiliate-network.sync.max_subjects', 500));

        $existingBySubject = $this->existingBySubject($site, (string) ($snapshot['program_id'] ?? ''));

        foreach (array_slice($snapshot['subjects'] ?? [], 0, $maxSubjects) as $subject) {
            try {
                $result = $this->syncSubject($site, $snapshot, $subject, $source, $existingBySubject);
            } catch (Throwable $exception) {
                Log::warning('affiliate-network.sync.subject_failed', [
                    'site_id' => $site->getKey(),
                    'program_id' => $snapshot['program_id'] ?? null,
                    'subject_key' => $subject['subject_key'] ?? null,
                    'message' => $exception->getMessage(),
                ]);

                $failed++;

                continue;
            }

            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                'locked' => $locked++,
                default => $skipped++,
            };
        }

        $site->update([
            'sync_status' => $failed > 0 ? 'partial' : 'ok',
            'last_synced_at' => CarbonImmutable::now(),
        ]);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'locked' => $locked, 'failed' => $failed];
    }

    /**
     * Sync every available program for a site (local or remote).
     *
     * One bad program never aborts the rest — failures are counted and the
     * site is stamped `partial` so the next run (or an operator) notices.
     *
     * @return array{programs: int, created: int, updated: int, skipped: int, locked: int, failed: int}
     */
    public function syncAll(AffiliateSite $site): array
    {
        $reader = $this->readerFor($site);

        $total = ['programs' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'locked' => 0, 'failed' => 0];

        $maxPrograms = max(1, (int) config('affiliate-network.sync.max_programs', 100));
        $programIds = array_slice($reader->programIds($site), 0, $maxPrograms);

        foreach ($programIds as $programId) {
            try {
                $result = $this->sync($site, $programId);
            } catch (Throwable $exception) {
                Log::warning('affiliate-network.sync.program_failed', [
                    'site_id' => $site->getKey(),
                    'program_id' => $programId,
                    'message' => $exception->getMessage(),
                ]);

                $total['failed']++;

                continue;
            }

            $total['programs']++;
            $total['created'] += $result['created'];
            $total['updated'] += $result['updated'];
            $total['skipped'] += $result['skipped'];
            $total['locked'] += $result['locked'];
            $total['failed'] += $result['failed'];
        }

        if ($total['failed'] > 0) {
            $site->update([
                'sync_status' => 'partial',
                'last_synced_at' => CarbonImmutable::now(),
            ]);
        }

        return $total;
    }

    private function readerFor(AffiliateSite $site): CatalogReaderInterface
    {
        return $this->readers->readerFor($site);
    }

    /**
     * Preload this program's offers once so syncing N subjects costs one
     * lookup query instead of N. Writes still go through the actions so the
     * rate lock, model hooks, and events keep firing (no blind upsert).
     *
     * @return array<string, AffiliateOffer>
     */
    private function existingBySubject(AffiliateSite $site, string $programId): array
    {
        if ($programId === '') {
            return [];
        }

        return AffiliateOffer::query()
            ->where('site_id', $site->getKey())
            ->where('external_program_id', $programId)
            ->get()
            ->keyBy(fn (AffiliateOffer $offer): string => (string) $offer->subject_key)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $subject
     * @param  array<string, AffiliateOffer>  $existingBySubject
     */
    private function syncSubject(AffiliateSite $site, array $snapshot, array $subject, string $source, array $existingBySubject): string
    {
        $subjectKey = (string) ($subject['subject_key'] ?? '');
        $effective = is_array($subject['effective'] ?? null) ? $subject['effective'] : [];
        $title = self::resolveField($source, $subject['title'] ?? null, $snapshot['title'] ?? null);
        // Catalogs may carry relative URLs; the domain only stores absolute
        // http(s) URLs, so anything else maps to null at this boundary.
        $url = self::absoluteHttpUrl(self::resolveField($source, $subject['url'] ?? null, $snapshot['url'] ?? null));
        $currency = self::resolveField($source, $subject['currency'] ?? null, $snapshot['currency'] ?? null);

        if ($subjectKey === '') {
            return 'skipped';
        }

        $extras = is_array($snapshot['variable_extras'] ?? null) ? $snapshot['variable_extras'] : [];
        $volumeTiers = $extras['volume_tiers'] ?? null;
        $promotions = $extras['promotions'] ?? null;

        $checksum = sha1((string) json_encode([
            'effective' => $effective,
            'title' => $title,
            'url' => $url,
            'currency' => $currency,
            'cookie_days' => $snapshot['cookie_days'] ?? null,
            'volume_tiers' => $volumeTiers,
            'active_promotions' => $promotions,
        ]));

        $existing = $existingBySubject[$subjectKey] ?? null;

        if ($existing && $existing->source_checksum === $checksum) {
            return 'skipped';
        }

        $programSuffix = mb_substr((string) str_replace('-', '', (string) ($snapshot['program_id'] ?? '')), 0, 8);

        $incomingRates = [
            'rate_base_bp' => ($effective['commission_type'] ?? 'percentage') === 'fixed'
                ? null
                : (int) ($effective['rate_bp'] ?? 0),
            'rate_fixed_minor' => ($effective['commission_type'] ?? null) === 'fixed'
                ? (int) ($effective['fixed_minor'] ?? 0)
                : null,
            'currency' => $currency,
            'cookie_days' => $snapshot['cookie_days'] ?? null,
            'volume_tiers' => AffiliateOffer::normalizeVolumeTiers(
                is_array($volumeTiers) ? $volumeTiers : null,
                is_string($currency) ? $currency : null,
            ),
            'active_promotions' => $promotions,
        ];

        $data = [
            'name' => mb_substr((string) ($title ?? $subjectKey), 0, 255),
            // Program suffix: same subject_key may appear in two programs on one site,
            // and slugs are unique per site.
            'slug' => Str::slug((string) ($subject['subject_type'] ?? 'item') . '-' . $subjectKey . '-' . $programSuffix),
            'description' => null,
            'status' => OfferStatus::Draft,
            'visibility' => OfferVisibility::Public,
            'rate_source' => 'synced',
            'landing_url' => $url,
            'source_url' => $url,
            'external_program_id' => (string) ($snapshot['program_id'] ?? ''),
            'subject_type' => $subject['subject_type'] ?? null,
            'subject_key' => $subjectKey,
            'source_checksum' => $checksum,
            'last_synced_at' => CarbonImmutable::now(),
            'metadata' => [
                'subject' => $subject,
                'catalog_source' => $source,
                'catalog_version' => $snapshot['version'] ?? 'v1',
            ],
        ] + $incomingRates;

        if ($existing) {
            // Lifecycle (status/visibility), slug, and description are
            // operator-owned once created — re-syncs must never unpublish,
            // rename, or blank them (the snapshot carries no description).
            unset($data['status'], $data['visibility'], $data['slug'], $data['description']);

            if ($existing->rate_source === 'manual') {
                // Never touch the lock itself here; only an explicit operator
                // write may flip it (see the model hook).
                unset($data['rate_source']);

                if (self::ratesDiffer($existing, $incomingRates)) {
                    // Operator overrode the rate block: hold every rate field
                    // back, but still refresh the non-rate mirror fields and
                    // stamp the incoming checksum so the next identical sync
                    // skips instead of re-reporting the lock.
                    foreach (array_keys($incomingRates) as $column) {
                        unset($data[$column]);
                    }

                    $this->write($existing, $data);

                    return 'locked';
                }
            }

            $this->write($existing, $data);

            return 'updated';
        }

        $this->write(null, $data, $site);

        return 'created';
    }

    private static function resolveField(string $source, mixed $local, mixed $remote): mixed
    {
        return match ($source) {
            'local' => $local ?? $remote,
            'remote' => $remote ?? $local,
            default => throw new InvalidArgumentException(sprintf('Unsupported catalog source [%s].', $source)),
        };
    }

    private static function absoluteHttpUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = mb_strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * @param  array<string, mixed>  $incomingRates
     */
    private static function ratesDiffer(AffiliateOffer $existing, array $incomingRates): bool
    {
        foreach ($incomingRates as $column => $incoming) {
            $current = $existing->getAttribute($column);

            if (is_array($incoming) || is_array($current)) {
                if ((array) $current !== (array) $incoming) {
                    return true;
                }

                continue;
            }

            if ((string) ($current ?? '') !== (string) ($incoming ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(?AffiliateOffer $existing, array $data, ?AffiliateSite $site = null): void
    {
        AffiliateOffer::setSyncingImport(true);

        try {
            DB::transaction(function () use ($existing, $data, $site): void {
                if ($existing) {
                    $this->updateOffer->execute($existing, $data);

                    return;
                }

                $this->createOffer->execute($site, $data);
            });
        } finally {
            AffiliateOffer::setSyncingImport(false);
        }
    }
}
