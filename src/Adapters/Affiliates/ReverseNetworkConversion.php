<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Adapters\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\Affiliates\Events\AffiliateConversionReversed;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Mirrors merchant reversals into network books without feeding them back.
 */
final class ReverseNetworkConversion
{
    public function __construct(
        private readonly NetworkBooks $books,
        private readonly AffiliateIdentityResolver $identities,
    ) {}

    public function handle(AffiliateConversionReversed $event): void
    {
        if (
            $event->origin !== AffiliatesLedgerPoster::SOURCE
            || ! is_string($event->sourceRef)
            || $event->sourceRef === ''
            || ! is_string($event->externalReference)
            || $event->externalReference === ''
        ) {
            return;
        }

        $leg = OwnerContext::withOwner(null, fn (): ?NetworkConversionLeg => NetworkConversionLeg::query()
            ->withoutOwnerScope()
            ->where('link_id', $event->sourceRef)
            ->where('external_reference', $event->externalReference)
            ->first());

        if (! $leg instanceof NetworkConversionLeg) {
            return;
        }

        $identity = $this->identities->find((string) $leg->affiliate_id);

        try {
            $owner = $identity === null
                ? null
                : OwnerContext::fromTypeAndIdOrFail($identity->ownerType, $identity->ownerId);
        } catch (InvalidArgumentException | ModelNotFoundException) {
            $owner = null;
        }

        OwnerContext::withOwner($owner, function () use ($leg, $event): void {
            $scopedLeg = NetworkConversionLeg::query()->whereKey($leg->getKey())->first();

            if ($scopedLeg instanceof NetworkConversionLeg) {
                $this->books->reverse($scopedLeg, $event->reason);
            }
        });
    }
}
