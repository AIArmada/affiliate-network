<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;

/**
 * Creator balances, derived from posted legs at read time.
 *
 * No balance table: legs are append-only, so every balance is a live
 * SUM grouped by currency. Only posted legs pay — provisional,
 * superseded, and reversed legs never count. If volume ever demands a
 * cache, it backfills exactly from these rows behind this same seam.
 */
final class CreatorBalances
{
    /**
     * @return array<string, int> Uppercase currency => payout minor.
     */
    public function for(string $affiliateId): array
    {
        /** @var array<string, int> $balances */
        $balances = [];

        foreach (NetworkConversionLeg::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', LegStatus::Posted)
            ->selectRaw('commission_currency, SUM(payout_minor) as total')
            ->groupBy('commission_currency')
            ->pluck('total', 'commission_currency') as $currency => $total) {
            $balances[mb_strtoupper((string) $currency)] = (int) $total;
        }

        return $balances;
    }
}
