<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Features\Attribution\Actions\IncrementLinkClicks;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\Links\Events\LinkClicked;

final class IncrementNetworkLinkClicks
{
    public function __construct(
        private readonly IncrementLinkClicks $incrementLinkClicks,
    ) {}

    public function handle(LinkClicked $event): void
    {
        if ($event->link->subject_type !== (new AffiliateOfferLink)->getMorphClass()) {
            return;
        }

        if ($event->click->is_bot) {
            return;
        }

        $this->incrementLinkClicks->execute((string) $event->link->subject_id);
    }
}
