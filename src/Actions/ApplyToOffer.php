<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Exceptions\ApplicationAlreadySubmittedException;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;

final class ApplyToOffer
{
    public function execute(AffiliateOffer $offer, Affiliate $affiliate, ?string $reason = null): AffiliateOfferApplication
    {
        $offer = AffiliateOffer::withoutGlobalScope('owner_via_site')
            ->whereKey($offer->getKey())
            ->firstOrFail();

        if (config('affiliates.owner.enabled', false)) {
            $affiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                (string) $affiliate->getKey(),
                includeGlobal: false,
                message: 'Affiliate is not accessible in the current owner scope.',
            );
        } else {
            $affiliate = Affiliate::query()->whereKey($affiliate->getKey())->firstOrFail();
        }

        $existing = AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->first();

        if ($existing !== null) {
            if ($existing->status === ApplicationStatus::Rejected) {
                $cooldownDays = config('affiliate-network.applications.cooldown_days', 7);
                $canReapply = CarbonImmutable::parse($existing->updated_at)->addDays($cooldownDays)->isPast();

                if (! $canReapply) {
                    throw ApplicationAlreadySubmittedException::forOffer((string) $offer->getKey());
                }

                $existing->update([
                    'status' => ApplicationStatus::Pending,
                    'reason' => $reason,
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

                $application = $existing->fresh();

                event(new ApplicationSubmitted($application));

                return $application;
            }

            return $existing;
        }

        $status = ApplicationStatus::Pending;

        if (! $offer->requires_approval || config('affiliate-network.applications.auto_approve', false)) {
            $status = ApplicationStatus::Approved;
        }

        $application = AffiliateOfferApplication::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $affiliate->id,
            'status' => $status,
            'reason' => $reason,
            'reviewed_at' => $status === ApplicationStatus::Approved ? CarbonImmutable::now() : null,
            'approved_at' => $status === ApplicationStatus::Approved ? CarbonImmutable::now() : null,
        ]);

        event(new ApplicationSubmitted($application));

        return $application;
    }
}
