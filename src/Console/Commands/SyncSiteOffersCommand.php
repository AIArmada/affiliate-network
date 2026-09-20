<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Console\Commands;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferImportService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class SyncSiteOffersCommand extends Command
{
    protected $signature = 'affiliate-network:sync-offers
                            {site : Site ID or domain}
                            {--program= : Program ID (omit to sync all available programs)}';

    protected $description = 'Sync merchant program catalog as network offers (local shared-DB or remote HTTP)';

    public function handle(OfferImportService $importer): int
    {
        if (! (bool) config('affiliate-network.sync.enabled', true)) {
            $this->error('Catalog sync is disabled (affiliate-network.sync.enabled).');

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('site');

        $site = $this->resolveSite($identifier);

        if (! $site) {
            $this->error("Site not found: {$identifier}");

            return self::FAILURE;
        }

        $programId = (string) ($this->option('program') ?? '');

        try {
            if ($programId !== '') {
                $result = OwnerContext::withOwner($site->owner, fn (): array => $importer->sync($site, $programId));
                $this->info("Offers synced: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped, {$result['locked']} locked, {$result['failed']} failed.");

                if ($result['failed'] > 0) {
                    $this->error("{$result['failed']} subject(s) failed; site marked partial.");

                    return self::FAILURE;
                }
            } else {
                $result = OwnerContext::withOwner($site->owner, fn (): array => $importer->syncAll($site));
                $this->info("Programs synced: {$result['programs']}; offers: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped, {$result['locked']} locked, {$result['failed']} failed.");

                if ($result['failed'] > 0) {
                    $this->error("{$result['failed']} program/subject failure(s); site marked partial.");

                    return self::FAILURE;
                }
            }
        } catch (Throwable $e) {
            OwnerContext::withOwner($site->owner, function () use ($site): void {
                $site->update(['sync_status' => 'failed', 'last_synced_at' => CarbonImmutable::now()]);
            });
            $this->error('Sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveSite(string $identifier): ?AffiliateSite
    {
        // Operator-supplied identifier: resolve outside any ambient owner
        // scope, then re-enter the site's own owner context for the sync.
        return OwnerContext::withOwner(null, fn (): ?AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey($identifier)
            ->orWhere('domain', $identifier)
            ->first());
    }
}
