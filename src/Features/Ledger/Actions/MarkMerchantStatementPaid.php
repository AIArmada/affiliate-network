<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MarkMerchantStatementPaid
{
    public function __construct(
        private readonly SitesApi $sites,
        private readonly Fulfillment $fulfillment,
        private readonly FulfillConversionLeg $fulfillConversionLeg,
    ) {}

    public function execute(string $statementId, ?string $paymentReference = null): MerchantStatement
    {
        $statement = DB::transaction(function () use ($statementId, $paymentReference): MerchantStatement {
            $statement = MerchantStatement::query()
                ->withoutGlobalScopes()
                ->whereKey($statementId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->status === SettlementStatus::Void) {
                throw new InvalidArgumentException('Void statements cannot be marked paid.');
            }

            if ($statement->status === SettlementStatus::Paid) {
                return $statement;
            }

            $statement->forceFill([
                'status' => SettlementStatus::Paid,
                'paid_at' => CarbonImmutable::now(),
                'payment_reference' => $paymentReference,
            ])->save();

            return $statement;
        });

        $this->fulfillAttachedLegs($statement);

        return $statement->refresh();
    }

    private function fulfillAttachedLegs(MerchantStatement $statement): void
    {
        $site = $this->sites->findByIdUnscoped((string) $statement->site_id);

        if ($site === null || ! $site->requireSettledForPayout || ! $this->fulfillment->fulfillsAutomatically()) {
            return;
        }

        $ids = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('statement_id', $statement->getKey())
            ->where('status', LegStatus::Posted)
            ->whereNull('fulfilled_at')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($ids as $id) {
            $this->fulfillConversionLeg->execute($id);
        }
    }
}
