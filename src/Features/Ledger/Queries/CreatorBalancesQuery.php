<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Queries;

use AIArmada\AffiliateNetwork\Data\CreatorBalanceSummary;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;

final class CreatorBalancesQuery
{
    /**
     * @return array<string, CreatorBalanceSummary>
     */
    public function for(string $affiliateId): array
    {
        $legs = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('affiliate_id', $affiliateId)
            ->get(['id', 'status', 'commission_currency', 'payout_minor', 'fulfilled_at', 'parent_leg_id', 'metadata']);

        /** @var array<string, array{pending: int, payable: int, paid: int, reversed: int, clawback: int}> $balances */
        $balances = [];

        $bucket = static function (string $currency) use (&$balances): void {
            $balances[$currency] ??= ['pending' => 0, 'payable' => 0, 'paid' => 0, 'reversed' => 0, 'clawback' => 0];
        };

        $reversalParents = [];

        foreach ($legs as $leg) {
            $currency = mb_strtoupper((string) $leg->commission_currency);

            if (is_array($leg->metadata) && isset($leg->metadata['reverses_id'])) {
                $reversalParents[(string) $leg->metadata['reverses_id']] = true;
            }
        }

        $fulfilledOriginals = [];

        foreach ($legs as $leg) {
            if ((string) $leg->parent_leg_id === '' && $leg->fulfilled_at !== null) {
                $fulfilledOriginals[(string) $leg->getKey()] = true;
            }
        }

        foreach ($legs as $leg) {
            $currency = mb_strtoupper((string) $leg->commission_currency);
            $bucket($currency);

            $isCompanion = $leg->parent_leg_id !== null;

            if ($leg->status === LegStatus::Pending) {
                $balances[$currency]['pending'] += (int) $leg->payout_minor;

                continue;
            }

            if ($leg->status === LegStatus::Posted) {
                if ($leg->fulfilled_at === null) {
                    $balances[$currency]['payable'] += (int) $leg->payout_minor;
                } else {
                    $balances[$currency]['paid'] += (int) $leg->payout_minor;
                }

                continue;
            }

            if ($leg->status === LegStatus::Reversed) {
                $balances[$currency]['reversed'] += (int) $leg->payout_minor;

                if ($isCompanion && isset($fulfilledOriginals[(string) $leg->parent_leg_id])) {
                    $balances[$currency]['clawback'] += (int) $leg->payout_minor;
                }
            }
        }

        $summaries = [];

        foreach ($balances as $currency => $totals) {
            $summaries[$currency] = new CreatorBalanceSummary(
                currency: $currency,
                pendingMinor: $totals['pending'],
                payableMinor: $totals['payable'],
                paidMinor: $totals['paid'],
                reversedMinor: $totals['reversed'],
                netPayableMinor: $totals['payable'] + $totals['clawback'],
            );
        }

        return $summaries;
    }
}
