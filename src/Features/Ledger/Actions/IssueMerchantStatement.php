<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Events\MerchantStatementIssued;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IssueMerchantStatement
{
    public function execute(string $statementId): MerchantStatement
    {
        $statement = DB::transaction(function () use ($statementId): MerchantStatement {
            $statement = MerchantStatement::query()
                ->withoutGlobalScopes()
                ->whereKey($statementId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->status !== SettlementStatus::Draft) {
                return $statement;
            }

            $issuedAt = CarbonImmutable::now();
            $dueDays = max(0, (int) config('affiliate-network.statements.due_days', 30));

            $statement->forceFill([
                'status' => SettlementStatus::Issued,
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->addDays($dueDays),
            ])->save();

            return $statement;
        });

        event(new MerchantStatementIssued($statement->toSummary()));

        return $statement;
    }

    public function assertIssuable(MerchantStatement $statement): void
    {
        if ($statement->status !== SettlementStatus::Draft) {
            throw new RuntimeException('Only draft statements can be issued.');
        }
    }
}
