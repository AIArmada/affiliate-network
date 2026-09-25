<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Console\Commands;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use Illuminate\Console\Command;

final class ReconcileNetworkLedgerCommand extends Command
{
    protected $signature = 'affiliate-network:reconcile
                            {--offer= : Offer ID (omit to reconcile every offer)}
                            {--fail-on-mismatch : Exit nonzero when any link mismatches}';

    protected $description = 'Prove network legs, counters, and merchant postings agree (counters, fee splits, payouts, dual-reporting)';

    public function handle(NetworkLedgerReconciliationService $reconciliation): int
    {
        $offerId = (string) ($this->option('offer') ?? '');

        $offers = $offerId !== ''
            ? AffiliateOffer::query()->whereKey($offerId)->get()
            : AffiliateOffer::query()->get();

        if ($offerId !== '' && $offers->isEmpty()) {
            $this->error("Offer not found: {$offerId}");

            return self::FAILURE;
        }

        $mismatched = 0;
        $links = 0;

        foreach ($offers as $offer) {
            $report = $reconciliation->reconcileOffer($offer);
            $links += $report['links'];

            if ($report['match']) {
                continue;
            }

            $mismatched++;

            $this->warn("Offer {$offer->getKey()}: {$report['matched_links']} of {$report['links']} links match.");

            foreach ($report['differences'] as $linkId => $differences) {
                foreach ($differences as $difference) {
                    $this->line("  [{$linkId}] {$difference}");
                }
            }
        }

        if ($mismatched === 0) {
            $this->info("All {$links} link(s) across {$offers->count()} offer(s) reconcile.");

            return self::SUCCESS;
        }

        $this->error("{$mismatched} offer(s) mismatch.");

        return $this->option('fail-on-mismatch') ? self::FAILURE : self::SUCCESS;
    }
}
