<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Console\Commands;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ArchiveExpiredOffersCommand extends Command
{
    protected $signature = 'affiliate-network:archive-expired
                          {--older-than=90 : Archive offers ended N days ago}
                          {--dry-run : Dry run without persisting changes}';

    protected $description = 'Archive expired affiliate network offers';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $olderThanDays = (int) $this->option('older-than');
        $threshold = CarbonImmutable::now()->subDays($olderThanDays);

        $this->info($dryRun ? 'DRY RUN: Archiving expired offers...' : 'Archiving expired offers...');

        $runner = new OwnerBatchRunner(AffiliateOffer::class, [
            'enabled' => 'affiliate-network.owner.enabled',
        ]);

        $total = $runner->forEach(function () use ($threshold, $dryRun): array {
            $expired = AffiliateOffer::query()
                ->where('ends_at', '<', $threshold)
                ->where('status', OfferStatus::Published)
                ->get();

            $archived = 0;

            foreach ($expired as $offer) {
                if (! $dryRun) {
                    $offer->update(['status' => OfferStatus::Archived]);
                }

                $archived++;
            }

            return ['archived' => $archived];
        });

        $totalArchived = collect($total)->sum('archived');

        $this->info("Offers archived: {$totalArchived}");

        Log::info('Expired offers archived', [
            'total' => $totalArchived,
            'older_than_days' => $olderThanDays,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
