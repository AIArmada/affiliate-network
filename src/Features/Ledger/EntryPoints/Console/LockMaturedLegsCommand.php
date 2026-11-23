<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Console;

use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class LockMaturedLegsCommand extends Command
{
    protected $signature = 'affiliate-network:lock-matured
                            {--as-of= : Lock legs matured at this timestamp (defaults to now)}';

    protected $description = 'Lock matured network conversion legs';

    public function handle(LockMaturedLegs $lockMaturedLegs): int
    {
        $asOf = $this->option('as-of');

        $locked = $lockMaturedLegs->execute(
            is_string($asOf) && $asOf !== '' ? CarbonImmutable::parse($asOf) : CarbonImmutable::now(),
        );

        $this->info('Legs locked: ' . count($locked));

        return self::SUCCESS;
    }
}
