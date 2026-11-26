<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\CatalogReader;
use AIArmada\AffiliateNetwork\Data\SiteCatalogEndpoint;
use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class AffiliatesCatalogDriver implements CatalogReader
{
    public function __construct(
        private readonly MerchantCatalog $catalog,
    ) {}

    public function snapshot(SiteCatalogEndpoint $site, string $programId): array
    {
        $snapshot = OwnerContext::withOwner(
            OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId),
            fn (): ?\AIArmada\Affiliates\Features\Programs\Api\ProgramSnapshot => $this->catalog->snapshotById($programId),
        );

        if ($snapshot === null) {
            throw new OfferNotFoundException('Program not found for catalog snapshot.');
        }

        return $snapshot->payload;
    }

    public function programIds(SiteCatalogEndpoint $site): array
    {
        $maxPrograms = max(1, (int) config('affiliate-network.sync.max_programs', 100));

        return OwnerContext::withOwner(
            OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId),
            fn (): array => $this->catalog->mirrorableProgramIds($maxPrograms),
        );
    }
}
