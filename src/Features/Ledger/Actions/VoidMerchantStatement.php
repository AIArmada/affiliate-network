<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class VoidMerchantStatement
{
    public function execute(string $statementId): MerchantStatement
    {
        return DB::transaction(function () use ($statementId): MerchantStatement {
            $statement = MerchantStatement::query()
                ->withoutGlobalScopes()
                ->whereKey($statementId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($statement->status, [SettlementStatus::Paid, SettlementStatus::Void], true)) {
                throw new InvalidArgumentException('Paid or void statements cannot be voided.');
            }

            NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->where('statement_id', $statement->getKey())
                ->update(['statement_id' => null]);

            $statement->forceFill(['status' => SettlementStatus::Void])->save();

            return $statement;
        });
    }
}
