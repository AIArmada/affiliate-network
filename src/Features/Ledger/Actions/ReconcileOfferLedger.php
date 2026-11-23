<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\LedgerReconciliationQuery;

final class ReconcileOfferLedger
{
    public function __construct(
        private readonly LedgerReconciliationQuery $reconciliation,
        private readonly AttributionApi $attribution,
    ) {}

    /**
     * @return array{offer_id: string, match: bool, links: int, matched_links: int, differences: array<string, list<string>>}
     */
    public function execute(string $offerId, bool $repairCounters = false): array
    {
        if (! $repairCounters) {
            return $this->reconciliation->reconcileOffer($offerId);
        }

        foreach ($this->attribution->offerLinkIds($offerId) as $linkId) {
            $this->reconciliation->computeCounters($linkId);
        }

        return $this->reconciliation->reconcileOffer($offerId);
    }
}
