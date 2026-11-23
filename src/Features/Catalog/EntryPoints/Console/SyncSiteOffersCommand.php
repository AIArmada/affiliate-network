<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\EntryPoints\Console;

use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncSiteOffers;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Illuminate\Console\Command;
use Throwable;

final class SyncSiteOffersCommand extends Command
{
    protected $signature = 'affiliate-network:sync-offers
                            {site : Site ID or domain}
                            {--program= : Program ID (omit to sync all available programs)}';

    protected $description = 'Sync merchant program catalog as network offers (local shared-DB or remote HTTP)';

    public function handle(SyncSiteOffers $syncSiteOffers, SitesApi $sites): int
    {
        if (! (bool) config('affiliate-network.sync.enabled', true)) {
            $this->error('Catalog sync is disabled (affiliate-network.sync.enabled).');

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('site');
        $site = $sites->findByIdOrDomainUnscoped($identifier);

        if ($site === null) {
            $this->error("Site not found: {$identifier}");

            return self::FAILURE;
        }

        $programId = (string) ($this->option('program') ?? '');

        try {
            if ($programId !== '') {
                $result = $syncSiteOffers->execute($site, $programId);
                $this->info("Offers synced: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped, {$result['locked']} locked, {$result['failed']} failed.");

                return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
            }

            $result = $syncSiteOffers->execute($site);
            $this->info("Programs synced: {$result['programs']}; offers: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped, {$result['locked']} locked, {$result['failed']} failed.");

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $sites->recordSyncStatus($site->id, SiteSyncStatus::Failed);
            $this->error('Sync failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
