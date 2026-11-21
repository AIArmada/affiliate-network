<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferImportService;
use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Operator catalog sync.
 *
 * Privileged system operation: enters the site's owner context so a console
 * or admin caller can sync any site without ambient tenant scope. Expose it
 * only from trusted operator surfaces.
 */
final class SyncSiteOffers
{
    public function __construct(
        private readonly OfferImportService $importer,
    ) {}

    /**
     * @return array<string, int>
     */
    public function execute(AffiliateSite $site, ?string $programId = null): array
    {
        $owner = OwnerContext::fromTypeAndIdOrFail($site->owner_type, $site->owner_id);

        return OwnerContext::withOwner($owner, function () use ($site, $programId): array {
            $site = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();

            return $programId !== null && $programId !== ''
                ? $this->importer->sync($site, $programId)
                : $this->importer->syncAll($site);
        });
    }
}
