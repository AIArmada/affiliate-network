<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Contracts\CatalogReader;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;
use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SyncSiteOffers
{
    public function __construct(
        private readonly CreateOffer $createOffer,
        private readonly UpdateOffer $updateOffer,
        private readonly SyncOfferCreatives $syncCreatives,
        private readonly SitesApi $sites,
    ) {}

    /**
     * @return array<string, int>
     */
    public function execute(SiteRecord $site, ?string $programId = null): array
    {
        $owner = OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId);

        return OwnerContext::withOwner($owner, function () use ($site, $programId): array {
            return $programId !== null && $programId !== ''
                ? $this->sync($site, $programId)
                : $this->syncAll($site);
        });
    }

    /**
     * @return array{created: int, updated: int, skipped: int, locked: int, failed: int}
     */
    public function sync(SiteRecord $site, string $programId): array
    {
        $snapshot = $this->readerFor($site)->snapshot($site->toCatalogEndpoint(), $programId);
        $source = $site->catalogUrl !== null && $site->catalogUrl !== '' ? 'remote' : 'local';

        $created = $updated = $skipped = $locked = $failed = 0;
        $maxSubjects = max(1, (int) config('affiliate-network.sync.max_subjects', 500));

        $existingBySubject = $this->existingBySubject($site, (string) ($snapshot['program_id'] ?? ''));
        $subjects = is_array($snapshot['subjects'] ?? null) ? $snapshot['subjects'] : [];

        foreach (array_slice($subjects, 0, $maxSubjects) as $subject) {
            if (! is_array($subject)) {
                $failed++;

                continue;
            }

            try {
                $result = DB::transaction(fn (): string => $this->syncSubject($site, $snapshot, $subject, $source, $existingBySubject));
            } catch (Throwable $exception) {
                Log::warning('affiliate-network.sync.subject_failed', [
                    'site_id' => $site->id,
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

        $this->sites->recordSyncStatus($site->id, $failed > 0 ? SiteSyncStatus::Partial : SiteSyncStatus::Ok);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'locked' => $locked, 'failed' => $failed];
    }

    /**
     * @return array{programs: int, created: int, updated: int, skipped: int, locked: int, failed: int}
     */
    public function syncAll(SiteRecord $site): array
    {
        $reader = $this->readerFor($site);

        $total = ['programs' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'locked' => 0, 'failed' => 0];

        $maxPrograms = max(1, (int) config('affiliate-network.sync.max_programs', 100));
        $programIds = array_slice($reader->programIds($site->toCatalogEndpoint()), 0, $maxPrograms);

        foreach ($programIds as $programId) {
            try {
                $result = $this->sync($site, $programId);
            } catch (Throwable $exception) {
                Log::warning('affiliate-network.sync.program_failed', [
                    'site_id' => $site->id,
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
            $this->sites->recordSyncStatus($site->id, SiteSyncStatus::Partial);
        }

        return $total;
    }

    private function readerFor(SiteRecord $site): CatalogReader
    {
        if ($site->catalogUrl !== null && $site->catalogUrl !== '') {
            if (! app()->bound(CatalogReader::class)) {
                throw AffiliatesNotInstalled::forFeature('remote catalog sync');
            }
        } elseif (! app()->bound('affiliate-network.catalog.local-reader')) {
            throw AffiliatesNotInstalled::forFeature('local catalog sync');
        }

        $reader = $site->catalogUrl !== null && $site->catalogUrl !== ''
            ? app(CatalogReader::class)
            : app('affiliate-network.catalog.local-reader');

        if (! $reader instanceof CatalogReader) {
            throw AffiliatesNotInstalled::forFeature('catalog sync');
        }

        return $reader;
    }

    /**
     * @return array<string, AffiliateOffer>
     */
    private function existingBySubject(SiteRecord $site, string $programId): array
    {
        if ($programId === '') {
            return [];
        }

        return AffiliateOffer::query()
            ->where('site_id', $site->id)
            ->where('external_program_id', $programId)
            ->get()
            ->keyBy(static fn (AffiliateOffer $offer): string => (string) $offer->subject_key)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $subject
     * @param  array<string, AffiliateOffer>  $existingBySubject
     */
    private function syncSubject(SiteRecord $site, array $snapshot, array $subject, string $source, array $existingBySubject): string
    {
        $subjectKey = (string) ($subject['subject_key'] ?? '');
        $effective = is_array($subject['effective'] ?? null) ? $subject['effective'] : [];
        $title = $this->resolveField($subject['title'] ?? null, $snapshot['title'] ?? null);
        $url = $this->absoluteHttpUrl($this->resolveField($subject['url'] ?? null, $snapshot['url'] ?? null));
        $currency = $this->resolveField($subject['currency'] ?? null, $snapshot['currency'] ?? null);

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

        $creatives = $this->syncCreatives->normalize($snapshot['creatives'] ?? null);
        $creativesChecksum = hash('sha256', (string) json_encode($creatives));

        $existing = isset($existingBySubject[$subjectKey])
            ? AffiliateOffer::query()->whereKey($existingBySubject[$subjectKey]->getKey())->lockForUpdate()->firstOrFail()
            : null;

        if ($existing instanceof AffiliateOffer && $existing->source_checksum === $checksum) {
            if ($existing->creatives_checksum === $creativesChecksum) {
                return 'skipped';
            }

            $this->syncCreatives->execute($existing, $creatives);
            $existing->forceFill(['creatives_checksum' => $creativesChecksum])->save();

            return 'updated';
        }

        $programSuffix = mb_substr((string) str_replace('-', '', (string) ($snapshot['program_id'] ?? '')), 0, 8);
        $commissionType = (string) ($effective['commission_type'] ?? 'percentage');

        $incomingRates = [
            'rate_base_bp' => $commissionType === 'fixed' ? null : (int) ($effective['rate_bp'] ?? 0),
            'rate_fixed_minor' => $commissionType === 'fixed' ? (int) ($effective['fixed_minor'] ?? 0) : null,
            'currency' => is_string($currency) ? $currency : null,
            'cookie_days' => $snapshot['cookie_days'] ?? null,
            'volume_tiers' => AffiliateOffer::normalizeVolumeTiers(
                is_array($volumeTiers) ? $volumeTiers : null,
                is_string($currency) ? $currency : null,
            ),
            'active_promotions' => $promotions,
        ];

        $data = [
            'name' => mb_substr((string) ($title ?? $subjectKey), 0, 255),
            'slug' => Str::slug((string) ($subject['subject_type'] ?? 'item') . '-' . $subjectKey . '-' . $programSuffix),
            'description' => null,
            'status' => OfferStatus::Draft,
            'visibility' => OfferVisibility::Public,
            'source' => 'mirrored',
            'landing_url' => $url,
            'source_url' => $url,
            'external_program_id' => (string) ($snapshot['program_id'] ?? ''),
            'subject_type' => $subject['subject_type'] ?? null,
            'subject_key' => $subjectKey,
            'source_checksum' => $checksum,
            'creatives_checksum' => $creativesChecksum,
            'last_synced_at' => CarbonImmutable::now(),
            'metadata' => [
                'subject' => $subject,
                'catalog_source' => $source,
                'catalog_version' => $snapshot['version'] ?? null,
            ],
        ] + $incomingRates;

        if ($existing instanceof AffiliateOffer) {
            unset($data['status'], $data['visibility'], $data['slug'], $data['description']);

            if ($existing->source->value === 'manual') {
                unset($data['source']);

                if (self::ratesDiffer($existing, $incomingRates)) {
                    foreach (array_keys($incomingRates) as $column) {
                        unset($data[$column]);
                    }

                    $offer = $this->updateOffer->execute($existing, $data, fromCatalogSync: true);
                    $this->syncCreatives->execute($offer, $creatives);

                    return 'locked';
                }
            }

            $offer = $this->updateOffer->execute($existing, $data, fromCatalogSync: true);
            $this->syncCreatives->execute($offer, $creatives);

            return 'updated';
        }

        $offer = $this->createOffer->execute($site, $data);
        $this->syncCreatives->execute($offer, $creatives);

        return 'created';
    }

    private function resolveField(mixed $subjectValue, mixed $snapshotValue): mixed
    {
        return $subjectValue ?? $snapshotValue;
    }

    private function absoluteHttpUrl(mixed $url): ?string
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
}
