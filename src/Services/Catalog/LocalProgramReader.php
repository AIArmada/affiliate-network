<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services\Catalog;

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\ProgramCatalogService;

/**
 * Local reader for shared-DB installs. Calls affiliates' catalog service
 * directly — no HTTP. Only resolves when aiarmada/affiliates is installed.
 * The reader is strictly read-only: ProgramCatalogService::snapshot() reads
 * catalog data and never records attribution, commission, or payout activity.
 */
final class LocalProgramReader implements CatalogReaderInterface
{
    public function snapshot(AffiliateSite $site, string $programId): array
    {
        if (! class_exists(AffiliateProgram::class)) {
            throw new OfferNotFoundException('Affiliates package not installed for local catalog read.');
        }

        $program = AffiliateProgram::query()->whereKey($programId)->first();

        if (! $program) {
            throw new OfferNotFoundException('Program not found for catalog snapshot.');
        }

        return app(ProgramCatalogService::class)->snapshot($program);
    }

    public function programIds(AffiliateSite $site): array
    {
        if (! class_exists(AffiliateProgram::class)) {
            throw new OfferNotFoundException('Affiliates package not installed for local catalog read.');
        }

        return AffiliateProgram::query()
            ->active()
            ->public()
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }
}
