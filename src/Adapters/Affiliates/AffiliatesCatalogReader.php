<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Adapters\Affiliates;

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderInterface;
use AIArmada\Affiliates\Contracts\MerchantCatalog;
use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Local catalog reader for shared-DB installs.
 *
 * Calls the merchant catalog seam directly — no HTTP. Strictly
 * read-only: snapshots never record attribution, commission, or
 * payout activity. A site mirrors its own owner's programs.
 */
final class AffiliatesCatalogReader implements CatalogReaderInterface
{
    public function __construct(
        private readonly MerchantCatalog $catalog,
    ) {}

    public function snapshot(AffiliateSite $site, string $programId): array
    {
        return OwnerContext::withOwner($site->owner, function () use ($programId): array {
            $snapshot = $this->catalog->snapshotById($programId);

            if ($snapshot === null) {
                throw new OfferNotFoundException('Program not found for catalog snapshot.');
            }

            return $snapshot->payload;
        });
    }

    public function programIds(AffiliateSite $site): array
    {
        $maxPrograms = max(1, (int) config('affiliate-network.sync.max_programs', 100));

        return OwnerContext::withOwner($site->owner, fn (): array => $this->catalog->mirrorableProgramIds($maxPrograms));
    }
}
