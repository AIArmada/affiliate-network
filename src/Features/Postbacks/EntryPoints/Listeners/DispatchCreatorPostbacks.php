<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Events\ConversionAdjusted;
use AIArmada\AffiliateNetwork\Events\ConversionLocked;
use AIArmada\AffiliateNetwork\Events\ConversionReversed;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\EnqueueCreatorPostbacks;

final class DispatchCreatorPostbacks
{
    public function __construct(
        private readonly EnqueueCreatorPostbacks $enqueue,
    ) {}

    public function handle(object $event): void
    {
        if ($event instanceof NetworkConversionRecorded) {
            $this->enqueue->execute($event->leg, PostbackEventType::ConversionRecorded);

            return;
        }

        if ($event instanceof ConversionLocked) {
            $this->enqueue->execute($event->leg, PostbackEventType::ConversionLocked);

            return;
        }

        if ($event instanceof ConversionReversed) {
            $this->enqueue->execute($event->reversalLeg, PostbackEventType::ConversionReversed);

            return;
        }

        if ($event instanceof ConversionAdjusted) {
            $this->enqueue->execute($event->adjustmentLeg, PostbackEventType::ConversionAdjusted);
        }
    }
}
