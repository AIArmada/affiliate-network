<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Queries;

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use Illuminate\Support\Collection;

final class LedgerReconciliationQuery
{
    public function __construct(
        private readonly AttributionApi $attribution,
        private readonly ?NetworkLedger $ledger = null,
    ) {}

    /**
     * @return array{link_id: string, match: bool, network: array{conversions: int, revenue_minor: int, currency: string|null}, legs: array{total: int, posted: int, provisional: int, superseded: int, reversed: int}, differences: list<string>}
     */
    public function reconcileLink(string $linkId): array
    {
        $link = $this->attribution->findLinkByIdUnscoped($linkId);

        /** @var Collection<int, NetworkConversionLeg> $legs */
        $legs = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('link_id', $linkId)
            ->get();

        $differences = [];

        $this->proveCounters($link, $legs, $differences);
        $this->proveFeeLegs($legs, $differences);

        if ($this->ledger !== null) {
            $this->proveMerchantPostings($linkId, $legs, $differences);
        }

        $byStatus = $legs->countBy(fn (NetworkConversionLeg $leg): string => $leg->status->value);

        return [
            'link_id' => $linkId,
            'match' => $differences === [],
            'network' => [
                'conversions' => $link instanceof OfferLinkRecord ? $link->conversions : 0,
                'revenue_minor' => $link instanceof OfferLinkRecord ? $link->revenue : 0,
                'currency' => $link instanceof OfferLinkRecord && $link->currency !== null ? mb_strtoupper($link->currency) : null,
            ],
            'legs' => [
                'total' => $legs->count(),
                'posted' => (int) $byStatus->get(LegStatus::Posted->value, 0),
                'provisional' => (int) $byStatus->get(LegStatus::Provisional->value, 0),
                'superseded' => (int) $byStatus->get(LegStatus::Superseded->value, 0),
                'reversed' => (int) $byStatus->get(LegStatus::Reversed->value, 0),
            ],
            'differences' => $differences,
        ];
    }

    /**
     * @return array{offer_id: string, match: bool, links: int, matched_links: int, differences: array<string, list<string>>}
     */
    public function reconcileOffer(string $offerId): array
    {
        $linkIds = $this->attribution->offerLinkIds($offerId);
        $differences = [];
        $matched = 0;

        foreach ($linkIds as $linkId) {
            $report = $this->reconcileLink($linkId);

            if ($report['match']) {
                $matched++;
            } else {
                $differences[$linkId] = $report['differences'];
            }
        }

        return [
            'offer_id' => $offerId,
            'match' => $differences === [],
            'links' => count($linkIds),
            'matched_links' => $matched,
            'differences' => $differences,
        ];
    }

    /**
     * @return array{conversions: int, revenue_minor: int}
     */
    public function computeCounters(string $linkId): array
    {
        $legs = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('link_id', $linkId)
            ->get();

        $link = $this->attribution->findLinkByIdUnscoped($linkId);
        $linkCurrency = $link instanceof OfferLinkRecord && $link->currency !== null
            ? mb_strtoupper($link->currency)
            : null;

        $conversions = 0;
        $revenue = 0;

        foreach ($legs as $leg) {
            if (is_array($leg->metadata) && isset($leg->metadata['reverses_id'])) {
                continue;
            }

            $conversions++;

            $legCurrency = $leg->revenue_currency !== null ? mb_strtoupper($leg->revenue_currency) : null;

            if ($linkCurrency === null || $legCurrency === null || $legCurrency === $linkCurrency) {
                $revenue += (int) $leg->revenue_minor;
            }
        }

        $this->attribution->setLinkCounters($linkId, $conversions, $revenue);

        return ['conversions' => $conversions, 'revenue_minor' => $revenue];
    }

    /**
     * @param  Collection<int, NetworkConversionLeg>  $legs
     * @param  list<string>  $differences
     */
    private function proveCounters(?OfferLinkRecord $link, Collection $legs, array &$differences): void
    {
        if (! $link instanceof OfferLinkRecord) {
            return;
        }

        $counted = $legs->reject(fn (NetworkConversionLeg $leg): bool => is_array($leg->metadata)
            && isset($leg->metadata['reverses_id']));

        if ($counted->count() !== $link->conversions) {
            $differences[] = sprintf(
                'conversion count differs: network %d vs legs %d',
                $link->conversions,
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

        if ($expected !== $link->revenue) {
            $differences[] = sprintf(
                'revenue cache differs: network %d vs legs %d',
                $link->revenue,
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
     */
    private function proveMerchantPostings(string $linkId, Collection $legs, array &$differences): void
    {
        if ($this->ledger === null) {
            return;
        }

        $rows = $this->ledger->rowsForLink($linkId);

        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = mb_strtoupper((string) ($row['commission_currency'] ?? ''));
            $byCurrency[$currency] ??= ['conversions' => 0, 'commission_minor' => 0];
            $byCurrency[$currency]['conversions']++;
            $byCurrency[$currency]['commission_minor'] += (int) ($row['commission_minor'] ?? 0);
        }

        $posted = $legs->where('status', LegStatus::Posted);
        $payoutByCurrency = [];

        foreach ($posted as $leg) {
            $currency = mb_strtoupper((string) $leg->commission_currency);
            $payoutByCurrency[$currency] ??= ['conversions' => 0, 'commission_minor' => 0];
            $payoutByCurrency[$currency]['conversions']++;
            $payoutByCurrency[$currency]['commission_minor'] += (int) $leg->payout_minor;
        }

        $currencies = array_values(array_unique([...array_keys($byCurrency), ...array_keys($payoutByCurrency)]));
        sort($currencies);

        foreach ($currencies as $currency) {
            $expected = $payoutByCurrency[$currency] ?? ['conversions' => 0, 'commission_minor' => 0];
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
            $postings = $this->ledger->postingsForExternalReference((string) $leg->external_reference);
            $origins = array_unique(array_map(
                static fn (array $row): string => (string) ($row['origin'] ?? '') . '|' . (string) ($row['source_ref'] ?? ''),
                $postings,
            ));

            if (count($origins) > 1) {
                $differences[] = sprintf(
                    'dual reporting on %s: paid by %s',
                    (string) $leg->external_reference,
                    implode(', ', $origins),
                );
            }
        }
    }
}
