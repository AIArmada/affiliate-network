<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class IncrementLinkClicks
{
    public function __construct(
        private readonly AffiliateIdentityResolver $identities,
    ) {}

    public function execute(string $linkId): void
    {
        $offerLink = AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereKey($linkId)
            ->first();

        if (! $offerLink instanceof AffiliateOfferLink) {
            return;
        }

        $owner = $this->identities->find((string) $offerLink->affiliate_id);
        $ownerModel = $owner === null
            ? null
            : OwnerContext::fromTypeAndId($owner->ownerType, $owner->ownerId);

        OwnerContext::withOwner($ownerModel, function () use ($linkId): void {
            AffiliateOfferLink::query()
                ->withoutGlobalScopes()
                ->whereKey($linkId)
                ->increment('clicks');
        });
    }
}
