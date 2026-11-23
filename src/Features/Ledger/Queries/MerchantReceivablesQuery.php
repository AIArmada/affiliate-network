<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Queries;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;

final class MerchantReceivablesQuery
{
    /**
     * @return array{unbilled: array<string, array{site_id: string, currency: string, legs: int, commission_minor: int, fee_minor: int, payout_minor: int}>, outstanding: array<string, array{site_id: string, currency: string, statements: int, payout_minor: int}>}
     */
    public function execute(): array
    {
        $unbilled = [];

        $rows = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('status', LegStatus::Posted)
            ->whereNull('statement_id')
            ->whereNull('parent_leg_id')
            ->selectRaw('site_id, commission_currency, COUNT(*) as legs, SUM(commission_minor) as commission_minor, SUM(fee_minor) as fee_minor, SUM(payout_minor) as payout_minor')
            ->groupBy('site_id', 'commission_currency')
            ->get();

        foreach ($rows as $row) {
            /** @var array{site_id: string, commission_currency: string, legs: int|string, commission_minor: int|string, fee_minor: int|string, payout_minor: int|string} $row */
            $key = (string) $row['site_id'] . '|' . mb_strtoupper((string) $row['commission_currency']);
            $unbilled[$key] = [
                'site_id' => (string) $row['site_id'],
                'currency' => mb_strtoupper((string) $row['commission_currency']),
                'legs' => (int) $row['legs'],
                'commission_minor' => (int) $row['commission_minor'],
                'fee_minor' => (int) $row['fee_minor'],
                'payout_minor' => (int) $row['payout_minor'],
            ];
        }

        $outstanding = [];

        $statements = MerchantStatement::query()
            ->withoutGlobalScopes()
            ->where('status', SettlementStatus::Issued)
            ->selectRaw('site_id, currency, COUNT(*) as statements, SUM(payout_minor) as payout_minor')
            ->groupBy('site_id', 'currency')
            ->get();

        foreach ($statements as $row) {
            /** @var array{site_id: string, currency: string, statements: int|string, payout_minor: int|string} $row */
            $key = (string) $row['site_id'] . '|' . mb_strtoupper((string) $row['currency']);
            $outstanding[$key] = [
                'site_id' => (string) $row['site_id'],
                'currency' => mb_strtoupper((string) $row['currency']),
                'statements' => (int) $row['statements'],
                'payout_minor' => (int) $row['payout_minor'],
            ];
        }

        return ['unbilled' => $unbilled, 'outstanding' => $outstanding];
    }
}
