<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\NotificationDispatcher;
use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;

final class NotifyNetworkConversion
{
    public function __construct(
        private readonly AffiliateIdentityResolver $identities,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(NetworkConversionRecorded $event): void
    {
        $leg = $event->leg;

        if (! $leg->status->paysOut() || $leg->payoutMinor <= 0) {
            return;
        }

        if (($leg->metadata['unfulfillable'] ?? null) !== null
            || ($leg->metadata['reconciliation_required'] ?? false) === true) {
            return;
        }

        $affiliate = $this->identities->find($leg->affiliateId);

        if ($affiliate === null) {
            return;
        }

        $this->dispatcher->dispatchToUser(
            $affiliate->userId ?? $affiliate->id,
            $affiliate->email,
            'affiliate-network.conversion_recorded',
            'network_conversion_leg',
            $leg->id,
            'network-conversion:' . $leg->id,
            new NotificationPayload(
                title: 'New conversion recorded',
                body: sprintf('A conversion worth %d %s was recorded for offer %s.', $leg->payoutMinor, $leg->commissionCurrency, $leg->offerId),
                context: [
                    'leg_id' => $leg->id,
                    'offer_id' => $leg->offerId,
                    'payout_minor' => $leg->payoutMinor,
                    'currency' => $leg->commissionCurrency,
                ],
            ),
        );
    }
}
