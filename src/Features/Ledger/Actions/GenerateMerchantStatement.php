<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateMerchantStatement
{
    public function __construct(
        private readonly SitesApi $sites,
    ) {}

    public function execute(string $siteId, string $currency, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): MerchantStatement
    {
        $site = $this->sites->findByIdUnscoped($siteId);
        $owner = OwnerContext::fromTypeAndId($site?->ownerType, $site?->ownerId);
        $ownerAttributes = ['owner_type' => $site?->ownerType, 'owner_id' => $site?->ownerId];

        $generate = function () use ($siteId, $currency, $periodStart, $periodEnd, $ownerAttributes): MerchantStatement {
            return DB::transaction(function () use ($siteId, $currency, $periodStart, $periodEnd, $ownerAttributes): MerchantStatement {
                $currency = mb_strtoupper($currency);

                $legs = NetworkConversionLeg::query()
                    ->withoutGlobalScopes()
                    ->where('site_id', $siteId)
                    ->where('commission_currency', $currency)
                    ->where('status', LegStatus::Posted)
                    ->whereNull('statement_id')
                    ->whereNull('parent_leg_id')
                    ->whereBetween('occurred_at', [$periodStart, $periodEnd])
                    ->orderBy('occurred_at')
                    ->lockForUpdate()
                    ->get();

                $statement = MerchantStatement::create($ownerAttributes + [
                    'site_id' => $siteId,
                    'statement_number' => $this->statementNumber($siteId, $periodEnd),
                    'status' => SettlementStatus::Draft,
                    'currency' => $currency,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'leg_count' => $legs->count(),
                    'gross_revenue_minor' => (int) $legs->sum('revenue_minor'),
                    'commission_minor' => (int) $legs->sum('commission_minor'),
                    'fee_minor' => (int) $legs->sum('fee_minor'),
                    'payout_minor' => (int) $legs->sum('payout_minor'),
                ]);

                if ($legs->isNotEmpty()) {
                    NetworkConversionLeg::query()
                        ->withoutGlobalScopes()
                        ->whereIn('id', $legs->modelKeys())
                        ->update(['statement_id' => $statement->getKey()]);
                }

                return $statement->refresh();
            });
        };

        return OwnerContext::withOwner($owner, $generate);
    }

    private function statementNumber(string $siteId, CarbonImmutable $periodEnd): string
    {
        $base = sprintf('MS-%s-%s', mb_substr($siteId, 0, 8), $periodEnd->format('Ym'));
        $candidate = $base;
        $suffix = 1;

        while (MerchantStatement::query()->withoutGlobalScopes()->where('statement_number', $candidate)->exists()) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }

        return $candidate;
    }
}
