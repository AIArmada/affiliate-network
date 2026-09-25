<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Events\LinkClicked;

/**
 * Mirror human clicks from tracked links onto network counters.
 *
 * Bot hits are recorded as click events but never inflate the counter.
 */
final class IncrementNetworkLinkClicks
{
    public function __construct(
        private readonly ?AffiliateIdentityResolver $identities = null,
    ) {}

    public function handle(LinkClicked $event): void
    {
        if ($event->link->subject_type !== (new AffiliateOfferLink)->getMorphClass()) {
            return;
        }

        if ($event->click->is_bot) {
            return;
        }

        $offerLink = AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->find($event->link->subject_id);

        if (! $offerLink instanceof AffiliateOfferLink) {
            return;
        }

        $owner = $this->identities?->find((string) $offerLink->affiliate_id)?->owner();

        OwnerContext::withOwner($owner, static function () use ($offerLink): void {
            $offerLink->incrementClicks();
        });
    }
}
