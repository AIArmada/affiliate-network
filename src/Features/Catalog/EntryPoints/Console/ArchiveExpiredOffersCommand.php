<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\EntryPoints\Console;

use AIArmada\AffiliateNetwork\Features\Catalog\Actions\ArchiveExpiredOffers;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class ArchiveExpiredOffersCommand extends Command
{
    protected $signature = 'affiliate-network:archive-expired
                          {--older-than=90 : Archive offers ended N days ago}
                          {--dry-run : Dry run without persisting changes}';

    protected $description = 'Archive expired affiliate network offers';

    public function handle(ArchiveExpiredOffers $archiveExpiredOffers): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $olderThanDays = max(0, (int) $this->option('older-than'));
        $threshold = CarbonImmutable::now()->subDays($olderThanDays);

        $siteModel = config('affiliate-network.models.site');

        if (! is_string($siteModel) || ! class_exists($siteModel) || ! is_subclass_of($siteModel, Model::class)) {
            $this->error('Affiliate site model is not configured.');

            return self::FAILURE;
        }

        /** @var class-string<Model> $siteModel */
        $runner = new OwnerBatchRunner($siteModel, [
            'enabled' => 'affiliate-network.owner.enabled',
        ]);

        $results = $runner->forEach(static fn (): array => [
            'archived' => $archiveExpiredOffers->execute($threshold, $dryRun),
        ]);

        $total = (int) $results->sum(static fn (array $result): int => (int) ($result['archived'] ?? 0));

        $this->info("Offers archived: {$total}");

        Log::info('Expired offers archived', [
            'total' => $total,
            'older_than_days' => $olderThanDays,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
