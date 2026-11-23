<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use Carbon\CarbonImmutable;

final class ArchiveExpiredOffers
{
    public function __construct(
        private readonly ArchiveOffer $archiveOffer,
    ) {}

    public function execute(CarbonImmutable $threshold, bool $dryRun = false): int
    {
        $archived = 0;

        AffiliateOffer::query()
            ->where('ends_at', '<', $threshold)
            ->where('status', OfferStatus::Published)
            ->chunkById(500, function ($offers) use ($dryRun, &$archived): void {
                foreach ($offers as $offer) {
                    if (! $offer instanceof AffiliateOffer) {
                        continue;
                    }

                    if (! $dryRun) {
                        $this->archiveOffer->execute($offer);
                    }

                    $archived++;
                }
            });

        return $archived;
    }
}
