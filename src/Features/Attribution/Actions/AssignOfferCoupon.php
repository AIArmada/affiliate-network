<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Platform\AffiliateProfileGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssignOfferCoupon
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly EnrollmentApi $enrollments,
        private readonly AffiliateProfileGuard $affiliateProfileGuard,
    ) {}

    public function execute(
        string $offerId,
        string $affiliateId,
        string $code,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        ?array $metadata = null,
    ): AffiliateOfferCoupon {
        $this->affiliateProfileGuard->assertCanWriteFor($affiliateId);

        $normalized = mb_strtoupper(mb_trim($code));

        if (preg_match('/^[A-Z0-9_-]{1,64}$/', $normalized) !== 1) {
            throw new RuntimeException('Coupon codes must be alphanumeric with hyphens or underscores, at most 64 characters.');
        }

        $offer = $this->catalog->findOfferUnscoped($offerId);

        if (! $offer instanceof OfferRecord || ! $offer->isActiveAt(CarbonImmutable::now())) {
            throw new RuntimeException('Coupons can only be assigned to active offers.');
        }

        if (! $this->sites->isVerified($offer->siteId)) {
            throw new RuntimeException('Coupons can only be assigned for verified sites.');
        }

        if ($offer->requiresApproval && ! $this->enrollments->isApprovedForOfferUnscoped($offer->id, $affiliateId)) {
            throw new RuntimeException('Affiliate is not approved for this offer.');
        }

        return DB::transaction(function () use ($offer, $affiliateId, $normalized, $startsAt, $endsAt, $metadata): AffiliateOfferCoupon {
            $exists = AffiliateOfferCoupon::query()
                ->withoutGlobalScopes()
                ->where('site_id', $offer->siteId)
                ->where('code', $normalized)
                ->exists();

            if ($exists) {
                throw new RuntimeException('Coupon code is already assigned for this site.');
            }

            return AffiliateOfferCoupon::create([
                'offer_id' => $offer->id,
                'site_id' => $offer->siteId,
                'affiliate_id' => $affiliateId,
                'code' => $normalized,
                'is_active' => true,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'metadata' => $metadata,
            ]);
        });
    }
}
