<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Collection;

/**
 * Reconcile network link counters against ledger postings.
 *
 * Links hold aggregate counters while the ledger holds one row per posted
 * conversion. This report joins them on network_link_id per currency leg
 * so finance can prove every counted conversion paid out exactly once.
 */
final class NetworkLedgerReconciliationService
{
    /**
     * @return array{link_id: string, match: bool, network: array{conversions: int, revenue_minor: int, currency: string|null}, ledger: array{conversions: int, by_currency: array<string, array{conversions: int, value_minor: int, commission_minor: int}>}, differences: list<string>}
     */
    public function reconcileLink(AffiliateOfferLink $link): array
    {
        /** @var Collection<int, AffiliateConversion> $rows */
        $rows = AffiliateConversion::query()
            ->where('network_link_id', $link->getKey())
            ->get(['commission_currency', 'value_minor', 'commission_minor']);

        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = mb_strtoupper((string) $row->commission_currency);

            $byCurrency[$currency] ??= ['conversions' => 0, 'value_minor' => 0, 'commission_minor' => 0];
            $byCurrency[$currency]['conversions']++;
            $byCurrency[$currency]['value_minor'] += (int) $row->value_minor;
            $byCurrency[$currency]['commission_minor'] += (int) $row->commission_minor;
        }

        ksort($byCurrency);

        $differences = [];

        if ($rows->count() !== (int) $link->conversions) {
            $differences[] = sprintf(
                'conversion count differs: network %d vs ledger %d',
                (int) $link->conversions,
                $rows->count(),
            );
        }

        if ($link->currency !== null) {
            $linkCurrency = mb_strtoupper($link->currency);
            $ledgerValue = $byCurrency[$linkCurrency]['value_minor'] ?? 0;

            if ($ledgerValue !== (int) $link->revenue) {
                $differences[] = sprintf(
                    'revenue differs in %s: network %d vs ledger %d',
                    $linkCurrency,
                    (int) $link->revenue,
                    $ledgerValue,
                );
            }
        }

        return [
            'link_id' => (string) $link->getKey(),
            'match' => $differences === [],
            'network' => [
                'conversions' => (int) $link->conversions,
                'revenue_minor' => (int) $link->revenue,
                'currency' => $link->currency !== null ? mb_strtoupper($link->currency) : null,
            ],
            'ledger' => [
                'conversions' => $rows->count(),
                'by_currency' => $byCurrency,
            ],
            'differences' => $differences,
        ];
    }

    /**
     * @return array{offer_id: string, match: bool, links: int, matched_links: int, differences: array<string, list<string>>}
     */
    public function reconcileOffer(AffiliateOffer $offer): array
    {
        $links = $offer->links()->get();
        $differences = [];
        $matched = 0;

        foreach ($links as $link) {
            $report = $this->reconcileLink($link);

            if ($report['match']) {
                $matched++;
            } else {
                $differences[(string) $link->getKey()] = $report['differences'];
            }
        }

        return [
            'offer_id' => (string) $offer->getKey(),
            'match' => $differences === [],
            'links' => $links->count(),
            'matched_links' => $matched,
            'differences' => $differences,
        ];
    }
}
