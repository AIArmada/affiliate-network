<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;

final class OfferManagementService
{
    /**
     * Create a new offer for a site.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOffer(AffiliateSite $site, array $data): AffiliateOffer
    {
        $data['site_id'] = $site->id;

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (empty($data['status'])) {
            $data['status'] = config('affiliate-network.offers.require_approval', true)
                ? AffiliateOffer::STATUS_PENDING
                : AffiliateOffer::STATUS_ACTIVE;
        }

        return AffiliateOffer::create($data);
    }

    /**
     * Apply for an offer as an affiliate.
     */
    public function applyForOffer(AffiliateOffer $offer, Affiliate $affiliate, ?string $reason = null): AffiliateOfferApplication
    {
        $existing = AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->first();

        if ($existing !== null) {
            if ($existing->status === AffiliateOfferApplication::STATUS_REJECTED) {
                $cooldownDays = config('affiliate-network.applications.cooldown_days', 7);
                $canReapply = $existing->updated_at->addDays($cooldownDays)->isPast();

                if (! $canReapply) {
                    throw new RuntimeException("Cannot reapply for this offer yet. Please wait {$cooldownDays} days after rejection.");
                }

                $existing->update([
                    'status' => AffiliateOfferApplication::STATUS_PENDING,
                    'reason' => $reason,
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

                return $existing->fresh();
            }

            return $existing;
        }

        $status = AffiliateOfferApplication::STATUS_PENDING;

        if (! $offer->requires_approval || config('affiliate-network.applications.auto_approve', false)) {
            $status = AffiliateOfferApplication::STATUS_APPROVED;
        }

        return AffiliateOfferApplication::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $affiliate->id,
            'status' => $status,
            'reason' => $reason,
            'reviewed_at' => $status === AffiliateOfferApplication::STATUS_APPROVED ? now() : null,
        ]);
    }

    /**
     * Approve an application.
     */
    public function approveApplication(AffiliateOfferApplication $application, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application->update([
            'status' => AffiliateOfferApplication::STATUS_APPROVED,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        return $application->fresh();
    }

    /**
     * Reject an application.
     */
    public function rejectApplication(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application->update([
            'status' => AffiliateOfferApplication::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        return $application->fresh();
    }

    /**
     * Revoke an approved application.
     */
    public function revokeApplication(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        $application->update([
            'status' => AffiliateOfferApplication::STATUS_REVOKED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        return $application->fresh();
    }

    /**
     * Check if an affiliate is approved for an offer.
     */
    public function isApprovedForOffer(AffiliateOffer $offer, Affiliate $affiliate): bool
    {
        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->where('status', AffiliateOfferApplication::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Get all offers an affiliate is approved for.
     *
     * @return Collection<int, AffiliateOffer>
     */
    public function getApprovedOffers(Affiliate $affiliate): Collection
    {
        $approvedOfferIds = AffiliateOfferApplication::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', AffiliateOfferApplication::STATUS_APPROVED)
            ->pluck('offer_id');

        return AffiliateOffer::query()
            ->whereIn('id', $approvedOfferIds)
            ->where('status', AffiliateOffer::STATUS_ACTIVE)
            ->get();
    }
}
