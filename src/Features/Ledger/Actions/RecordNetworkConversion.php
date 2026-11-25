<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordNetworkConversion
{
    public function __construct(
        private readonly PostConversionLeg $postConversionLeg,
        private readonly AttributionApi $attribution,
        private readonly SitesApi $sites,
        private readonly Fulfillment $fulfillment,
        private readonly FulfillConversionLeg $fulfillConversionLeg,
        private readonly AffiliateIdentityResolver $identities,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $offerId,
        string $affiliateId,
        int $revenueMinor,
        ?string $currency,
        string $externalReference,
        string $eventType = 'purchase',
        LegStatus $status = LegStatus::Posted,
        ?string $linkId = null,
        ?string $couponCode = null,
        ?string $linkCode = null,
        ?string $subId = null,
        ?string $subId2 = null,
        ?string $subId3 = null,
        array $metadata = [],
        ?string $couponId = null,
    ): NetworkConversionLeg {
        if (mb_trim($externalReference) === '') {
            throw new InvalidArgumentException('Network conversions require a non-empty external reference.');
        }

        $identity = $this->identities->find($affiliateId);
        $owner = $identity === null
            ? null
            : OwnerContext::fromTypeAndId($identity->ownerType, $identity->ownerId);

        return OwnerContext::withOwner($owner, fn (): NetworkConversionLeg => $this->record(
            offerId: $offerId,
            affiliateId: $affiliateId,
            revenueMinor: $revenueMinor,
            currency: $currency,
            externalReference: $externalReference,
            eventType: $eventType,
            status: $status,
            linkId: $linkId,
            couponCode: $couponCode,
            linkCode: $linkCode,
            subId: $subId,
            subId2: $subId2,
            subId3: $subId3,
            metadata: $metadata,
            couponId: $couponId,
        ));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        string $offerId,
        string $affiliateId,
        int $revenueMinor,
        ?string $currency,
        string $externalReference,
        string $eventType,
        LegStatus $status,
        ?string $linkId,
        ?string $couponCode,
        ?string $linkCode,
        ?string $subId,
        ?string $subId2,
        ?string $subId3,
        array $metadata,
        ?string $couponId,
    ): NetworkConversionLeg {
        $leg = DB::transaction(function () use (
            $offerId,
            $affiliateId,
            $revenueMinor,
            $currency,
            $externalReference,
            $eventType,
            $status,
            $linkId,
            $couponCode,
            $linkCode,
            $subId,
            $subId2,
            $subId3,
            $metadata,
            $couponId,
        ): NetworkConversionLeg {
            $leg = $this->postConversionLeg->execute(
                offerId: $offerId,
                affiliateId: $affiliateId,
                revenueMinor: $revenueMinor,
                currency: $currency,
                externalReference: $externalReference,
                eventType: $eventType,
                status: $status,
                linkId: $linkId,
                couponId: $couponId,
                couponCode: $couponCode,
                linkCode: $linkCode,
                subId: $subId,
                subId2: $subId2,
                subId3: $subId3,
                metadata: $metadata,
            );

            if ($linkId !== null && $linkId !== '' && $leg->wasRecentlyCreated) {
                $this->attribution->recordLinkCounters($linkId, $this->counterRevenue($leg), 1);
            }

            return $leg;
        });

        if (! $leg->wasRecentlyCreated) {
            return $leg;
        }

        if ($leg->status === LegStatus::Provisional) {
            return $leg;
        }

        if ($leg->status === LegStatus::Posted) {
            $site = $leg->site_id === null ? null : $this->sites->findByIdUnscoped((string) $leg->site_id);

            if ($site !== null && ! $site->requireSettledForPayout && $this->fulfillment->fulfillsAutomatically()) {
                $this->fulfillConversionLeg->execute((string) $leg->getKey());
                $leg = $leg->refresh();
            }
        }

        $this->dispatchRecorded($leg);

        return $leg;
    }

    private function counterRevenue(NetworkConversionLeg $leg): int
    {
        return (int) $leg->revenue_minor;
    }

    private function dispatchRecorded(NetworkConversionLeg $leg): void
    {
        $snapshot = $leg->toSnapshot();

        event(new NetworkConversionRecorded(
            leg: $snapshot,
            counterRevenueMinor: (int) $leg->revenue_minor,
            currency: $leg->revenue_currency,
        ));
    }
}
