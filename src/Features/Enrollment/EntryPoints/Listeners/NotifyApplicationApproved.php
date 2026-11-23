<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\NotificationDispatcher;
use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;

final class NotifyApplicationApproved
{
    public function __construct(
        private readonly AffiliateIdentityResolver $identities,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(ApplicationApproved $event): void
    {
        $affiliate = $this->identities->find($event->affiliateId);

        if ($affiliate === null) {
            return;
        }

        $payload = new NotificationPayload(
            title: 'Application approved',
            body: sprintf('Your application to %s has been approved.', $event->offerName),
            context: [
                'application_id' => $event->applicationId,
                'offer_id' => $event->offerId,
                'site_id' => $event->siteId,
            ],
        );

        $this->dispatcher->dispatchToUser(
            $affiliate->userId ?? $affiliate->id,
            $affiliate->email,
            'affiliate-network.application_approved',
            'affiliate_offer_application',
            $event->applicationId,
            'application-approved:' . $event->applicationId,
            $payload,
        );
    }
}
