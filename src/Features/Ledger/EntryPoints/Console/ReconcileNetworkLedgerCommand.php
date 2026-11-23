<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Console;

use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReconcileOfferLedger;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class ReconcileNetworkLedgerCommand extends Command
{
    protected $signature = 'affiliate-network:reconcile
                            {--offer= : Offer ID (omit to reconcile every offer)}
                            {--repair : Repair link counters from ledger legs}
                            {--fail-on-mismatch : Exit nonzero when any link mismatches}';

    protected $description = 'Prove network legs, counters, and merchant postings agree';

    public function handle(ReconcileOfferLedger $reconcile): int
    {
        $offerModel = config('affiliate-network.models.offer');

        if (! is_string($offerModel) || ! class_exists($offerModel) || ! is_subclass_of($offerModel, Model::class)) {
            $this->error('Affiliate offer model is not configured.');

            return self::FAILURE;
        }

        $runner = new OwnerBatchRunner($offerModel, [
            'enabled' => 'affiliate-network.owner.enabled',
            'include_global' => 'affiliate-network.owner.include_global',
        ]);

        $offerId = (string) ($this->option('offer') ?? '');
        $repair = (bool) $this->option('repair');

        /** @var array{offers: int, links: int, mismatched: int} $totals */
        $totals = $runner->run(function () use ($offerModel, $offerId, $reconcile, $repair): array {
            $query = $offerModel::query();

            if ($offerId !== '') {
                $query->whereKey($offerId);
            }

            $offers = $query->get();

            $links = 0;
            $mismatched = 0;

            foreach ($offers as $offer) {
                $report = $reconcile->execute((string) $offer->getKey(), $repair);
                $links += $report['links'];

                if (! $report['match']) {
                    $mismatched++;
                }
            }

            return ['offers' => $offers->count(), 'links' => $links, 'mismatched' => $mismatched];
        });

        $this->info(sprintf(
            'Reconciled %d offer(s), %d link(s); %d offer(s) mismatched.',
            $totals['offers'],
            $totals['links'],
            $totals['mismatched'],
        ));

        if ($totals['mismatched'] > 0 && (bool) $this->option('fail-on-mismatch')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
