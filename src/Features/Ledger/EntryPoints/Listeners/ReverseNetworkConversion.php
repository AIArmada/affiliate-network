<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;

final class ReverseNetworkConversion
{
    public function __construct(
        private readonly LedgerApi $ledger,
        private readonly ReverseConversionLeg $reverseConversionLeg,
    ) {}

    public function handle(mixed $event): void
    {
        $origin = property_exists($event, 'origin') ? $event->origin : null;
        $sourceRef = property_exists($event, 'sourceRef') ? $event->sourceRef : null;
        $externalReference = property_exists($event, 'externalReference') ? $event->externalReference : null;
        $reason = property_exists($event, 'reason') && is_string($event->reason) ? $event->reason : 'refund';

        if ($origin !== 'marketplace'
            || ! is_string($sourceRef) || $sourceRef === ''
            || ! is_string($externalReference) || $externalReference === '') {
            return;
        }

        $leg = $this->ledger->findLegByLinkOrCouponUnscoped($sourceRef, null, $externalReference);

        if ($leg === null) {
            return;
        }

        $this->reverseConversionLeg->execute($leg->id, $reason);
    }
}
