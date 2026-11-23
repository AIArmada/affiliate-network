<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApproveApplication
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
    ) {}

    public function execute(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return DB::transaction(function () use ($application, $reviewedBy): AffiliateOfferApplication {
            $application = AffiliateOfferApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->isApproved()) {
                return $application;
            }

            if (! $application->isPending()) {
                throw new RuntimeException('Only pending applications can be approved.');
            }

            $offer = $this->catalog->findOfferUnscoped((string) $application->offer_id);

            if (! $offer instanceof OfferRecord || ! $this->sites->isVerified($offer->siteId)) {
                throw new RuntimeException('Applications can only be approved for verified sites.');
            }

            $application->update([
                'status' => ApplicationStatus::Approved,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => CarbonImmutable::now(),
                'approved_at' => CarbonImmutable::now(),
            ]);

            $fresh = $application->fresh() ?? $application;
            $site = $this->sites->findByIdUnscoped($offer->siteId);

            DB::afterCommit(fn () => event(new ApplicationApproved(
                applicationId: (string) $fresh->getKey(),
                offerId: $offer->id,
                offerName: $offer->name,
                siteId: $offer->siteId,
                affiliateId: (string) $fresh->affiliate_id,
                status: ApplicationStatus::Approved->value,
                ownerType: $site instanceof SiteRecord ? $site->ownerType : null,
                ownerId: $site instanceof SiteRecord ? $site->ownerId : null,
            )));

            return $fresh;
        });
    }
}
