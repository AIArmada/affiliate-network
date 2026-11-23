<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\NotificationDispatcher;
use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;

final class NotifyApplicationSubmitted
{
    public function __construct(
        private readonly SitesApi $sites,
        private readonly AffiliateIdentityResolver $identities,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(ApplicationSubmitted $event): void
    {
        $site = $this->sites->findByIdUnscoped($event->siteId);
        $affiliate = $this->identities->find($event->affiliateId);

        $payload = new NotificationPayload(
            title: 'New affiliate application',
            body: sprintf(
                '%s applied to offer %s.',
                $affiliate?->handle ?? $event->affiliateId,
                $event->offerName,
            ),
            context: [
                'application_id' => $event->applicationId,
                'offer_id' => $event->offerId,
                'site_id' => $event->siteId,
                'affiliate_id' => $event->affiliateId,
            ],
        );

        $this->dispatcher->dispatchToOwner(
            $event->ownerType ?? $site?->ownerType,
            $event->ownerId ?? $site?->ownerId,
            'affiliate-network.application_submitted',
            'affiliate_offer_application',
            $event->applicationId,
            'application-submitted:' . $event->applicationId,
            $payload,
        );
    }
}
