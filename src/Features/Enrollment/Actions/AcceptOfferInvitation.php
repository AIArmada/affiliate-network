<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use Carbon\CarbonImmutable;
use RuntimeException;

final class AcceptOfferInvitation
{
    public function __construct(
        private readonly ApplyToOffer $applyToOffer,
    ) {}

    public function execute(string $token, string $affiliateId, ?string $reason = null): AffiliateOfferApplication
    {
        $invitation = AffiliateOfferInvitation::query()
            ->withoutGlobalScopes()
            ->where('token', $token)
            ->first();

        if (! $invitation instanceof AffiliateOfferInvitation || ! $invitation->isActive()) {
            throw new RuntimeException('Invitation is no longer valid.');
        }

        $application = $this->applyToOffer->execute(
            (string) $invitation->offer_id,
            $affiliateId,
            $reason,
            $token,
        );

        $invitation->update(['accepted_at' => CarbonImmutable::now()]);

        return $application;
    }
}
