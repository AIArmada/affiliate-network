<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Console;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\GenerateMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class GenerateMerchantStatementsCommand extends Command
{
    protected $signature = 'affiliate-network:generate-statements
                            {--period-start= : Period start (defaults to the start of last month)}
                            {--period-end= : Period end (defaults to the end of last month)}
                            {--issue : Issue draft statements immediately}';

    protected $description = 'Generate merchant statements from posted conversion legs';

    public function handle(GenerateMerchantStatement $generate, IssueMerchantStatement $issue): int
    {
        $siteModel = config('affiliate-network.models.site');

        if (! is_string($siteModel) || ! class_exists($siteModel) || ! is_subclass_of($siteModel, Model::class)) {
            $this->error('Affiliate site model is not configured.');

            return self::FAILURE;
        }

        $periodStart = $this->resolveDate('period-start') ?? CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $periodEnd = $this->resolveDate('period-end') ?? $periodStart->endOfMonth();

        $runner = new OwnerBatchRunner($siteModel, [
            'enabled' => 'affiliate-network.owner.enabled',
            'include_global' => 'affiliate-network.owner.include_global',
        ]);

        $created = $runner->run(function () use ($generate, $issue, $periodStart, $periodEnd): int {
            $siteIds = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->where('status', LegStatus::Posted)
                ->whereNull('statement_id')
                ->whereBetween('occurred_at', [$periodStart, $periodEnd])
                ->distinct()
                ->pluck('site_id')
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '');

            $created = 0;

            foreach ($siteIds as $siteId) {
                $currencies = NetworkConversionLeg::query()
                    ->withoutGlobalScopes()
                    ->where('site_id', $siteId)
                    ->where('status', LegStatus::Posted)
                    ->whereNull('statement_id')
                    ->whereBetween('occurred_at', [$periodStart, $periodEnd])
                    ->distinct()
                    ->pluck('commission_currency');

                foreach ($currencies as $currency) {
                    $statement = $generate->execute((string) $siteId, (string) $currency, $periodStart, $periodEnd);
                    $created++;

                    if ((bool) $this->option('issue')) {
                        $issue->execute((string) $statement->getKey());
                    }
                }
            }

            return $created;
        });

        $this->info('Statements generated: ' . (int) $created);

        return self::SUCCESS;
    }

    private function resolveDate(string $option): ?CarbonImmutable
    {
        $value = $this->option($option);

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
