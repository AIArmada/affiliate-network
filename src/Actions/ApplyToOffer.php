<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Exceptions\ApplicationAlreadySubmittedException;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\ApprovedState;
use AIArmada\AffiliateNetwork\States\ApplicationStatusState\PendingState;
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
            if ($existing->status->isRejected()) {
                $cooldownDays = config('affiliate-network.applications.cooldown_days', 7);
                $canReapply = CarbonImmutable::parse($existing->rejected_at ?? $existing->updated_at)->addDays($cooldownDays)->isPast();

                if (! $canReapply) {
                    throw ApplicationAlreadySubmittedException::forOffer((string) $offer->getKey());
                }

                $existing->status->transitionTo(PendingState::class);
                $existing->reason = $reason;
                $existing->rejection_reason = null;
                $existing->reviewed_by = null;
                $existing->reviewed_at = null;
                $existing->save();

                $application = $existing->fresh();

                event(new ApplicationSubmitted($application));

                return $application;
            }

            return $existing;
        }

        $application = new AffiliateOfferApplication();
        $application->offer_id = $offer->id;
        $application->affiliate_id = $affiliate->id;
        $application->reason = $reason;
        $application->save();

        if (! $offer->requires_approval || config('affiliate-network.applications.auto_approve', false)) {
            $application->status->transitionTo(ApprovedState::class);
            $application->reviewed_at = CarbonImmutable::now();
            $application->approved_at = CarbonImmutable::now();
            $application->save();
        }

        event(new ApplicationSubmitted($application));

        return $application;
    }
}
