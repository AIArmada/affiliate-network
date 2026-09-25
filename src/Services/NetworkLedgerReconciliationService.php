<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Illuminate\Support\Collection;

/**
 * Prove network money exactly-once, in four layers.
 *
 * 1. Counters vs legs: cached link counters match derived leg totals.
 * 2. Fee legs: every leg splits cleanly (commission == payout + fee).
 * 3. Books vs merchant postings (ledger bound): posted payout legs
 *    match fulfilled merchant rows per currency.
 * 4. Collisions (ledger bound): no external reference paid by two
 *    origins or two source refs (dual-reporting flag).
 */
final class NetworkLedgerReconciliationService
{
    public function __construct(
        private readonly ?NetworkLedger $ledger = null,
    ) {}

    /**
     * @return array{link_id: string, match: bool, network: array{conversions: int, revenue_minor: int, currency: string|null}, legs: array{total: int, posted: int, provisional: int, superseded: int, reversed: int}, ledger: array{conversions: int, by_currency: array<string, array{conversions: int, value_minor: int, commission_minor: int}>}|null, differences: list<string>}
     */
    public function reconcileLink(AffiliateOfferLink $link): array
    {
        /** @var Collection<int, NetworkConversionLeg> $legs */
        $legs = NetworkConversionLeg::query()->where('link_id', $link->getKey())->get();

        $differences = [];
        $this->proveCounters($link, $legs, $differences);
        $this->proveFeeLegs($legs, $differences);

        $ledger = null;

        if ($this->ledger !== null) {
            $ledger = $this->proveMerchantPostings($link, $legs, $differences);
        }

        $byStatus = $legs->countBy(fn (NetworkConversionLeg $leg): string => $leg->status->value);

        return [
            'link_id' => (string) $link->getKey(),
            'match' => $differences === [],
            'network' => [
                'conversions' => (int) $link->conversions,
                'revenue_minor' => (int) $link->revenue,
                'currency' => $link->currency !== null ? mb_strtoupper($link->currency) : null,
            ],
            'legs' => [
                'total' => $legs->count(),
                'posted' => $byStatus->get(LegStatus::Posted->value, 0),
                'provisional' => $byStatus->get(LegStatus::Provisional->value, 0),
                'superseded' => $byStatus->get(LegStatus::Superseded->value, 0),
                'reversed' => $byStatus->get(LegStatus::Reversed->value, 0),
            ],
            'ledger' => $ledger,
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

    /**
     * @param  Collection<int, NetworkConversionLeg>  $legs
     * @param  list<string>  $differences
     */
    private function proveCounters(AffiliateOfferLink $link, Collection $legs, array &$differences): void
    {
        $counted = $legs->reject(fn (NetworkConversionLeg $leg): bool => is_array($leg->metadata)
            && isset($leg->metadata['reverses_id']));

        if ($counted->count() !== (int) $link->conversions) {
            $differences[] = sprintf(
                'conversion count differs: network %d vs legs %d',
                (int) $link->conversions,
                $counted->count(),
            );
        }

        $linkCurrency = $link->currency !== null ? mb_strtoupper($link->currency) : null;
        $expected = 0;

        foreach ($counted as $leg) {
            $legCurrency = $leg->revenue_currency !== null ? mb_strtoupper($leg->revenue_currency) : null;

            if ($linkCurrency === null || $legCurrency === null || $legCurrency === $linkCurrency) {
                $expected += (int) $leg->revenue_minor;
            }
        }

        if ($expected !== (int) $link->revenue) {
            $differences[] = sprintf(
                'revenue cache differs: network %d vs legs %d',
                (int) $link->revenue,
                $expected,
            );
        }
    }

    /**
     * @param  Collection<int, NetworkConversionLeg>  $legs
     * @param  list<string>  $differences
     */
    private function proveFeeLegs(Collection $legs, array &$differences): void
    {
        foreach ($legs as $leg) {
            if ((int) $leg->commission_minor !== (int) $leg->payout_minor + (int) $leg->fee_minor) {
                $differences[] = sprintf(
                    'leg %s splits dirty: commission %d != payout %d + fee %d',
                    (string) $leg->getKey(),
                    (int) $leg->commission_minor,
                    (int) $leg->payout_minor,
                    (int) $leg->fee_minor,
                );
            }
        }
    }

    /**
     * @param  Collection<int, NetworkConversionLeg>  $legs
     * @param  list<string>  $differences
     * @return array{conversions: int, by_currency: array<string, array{conversions: int, value_minor: int, commission_minor: int}>}
     */
    private function proveMerchantPostings(AffiliateOfferLink $link, Collection $legs, array &$differences): array
    {
        $rows = $this->ledger->rowsForLink((string) $link->getKey());

        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = mb_strtoupper((string) ($row['commission_currency'] ?? ''));
            $byCurrency[$currency] ??= ['conversions' => 0, 'value_minor' => 0, 'commission_minor' => 0];
            $byCurrency[$currency]['conversions']++;
            $byCurrency[$currency]['value_minor'] += (int) ($row['value_minor'] ?? 0);
            $byCurrency[$currency]['commission_minor'] += (int) ($row['commission_minor'] ?? 0);
        }

        ksort($byCurrency);

        $posted = $legs->where('status', LegStatus::Posted);

        // Merchant books carry the creator share (payout), not gross.
        $payoutByCurrency = [];

        foreach ($posted as $leg) {
            $currency = mb_strtoupper((string) $leg->commission_currency);
            $payoutByCurrency[$currency] ??= ['conversions' => 0, 'commission_minor' => 0];
            $payoutByCurrency[$currency]['conversions']++;
            $payoutByCurrency[$currency]['commission_minor'] += (int) $leg->payout_minor;
        }

        foreach ($payoutByCurrency as $currency => $expected) {
            $actual = $byCurrency[$currency] ?? ['conversions' => 0, 'commission_minor' => 0];

            if ($actual['conversions'] !== $expected['conversions']
                || $actual['commission_minor'] !== $expected['commission_minor']) {
                $differences[] = sprintf(
                    'merchant postings differ in %s: legs %d/%d vs postings %d/%d (count/payout)',
                    $currency,
                    $expected['conversions'],
                    $expected['commission_minor'],
                    $actual['conversions'],
                    $actual['commission_minor'],
                );
            }
        }

        foreach ($posted as $leg) {
            $this->proveNoCollision((string) $leg->external_reference, $differences);
        }

        return ['conversions' => count($rows), 'by_currency' => $byCurrency];
    }

    /**
     * @param  list<string>  $differences
     */
    private function proveNoCollision(string $externalReference, array &$differences): void
    {
        $postings = $this->ledger->postingsForExternalReference($externalReference);

        $origins = array_unique(array_map(
            fn (array $row): string => (string) ($row['origin'] ?? '') . '|' . (string) ($row['source_ref'] ?? ''),
            $postings,
        ));

        if (count($origins) > 1) {
            $differences[] = sprintf(
                'dual reporting on %s: paid by %s',
                $externalReference,
                implode(', ', $origins),
            );
        }
    }
}
