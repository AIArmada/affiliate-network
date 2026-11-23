<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;

final class DeleteOfferDependents
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly AttributionApi $attribution,
    ) {}

    public function execute(string $offerId): void
    {
        AffiliateOfferCreative::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->get()
            ->each(static fn (AffiliateOfferCreative $creative) => $creative->delete());

        $this->enrollments->deleteForOffer($offerId);
        $this->attribution->deleteForOffer($offerId);
    }
}
