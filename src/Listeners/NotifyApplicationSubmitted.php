<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Notifications\ApplicationSubmittedNotification;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;

/**
 * Notifies the site owner when a creator applies.
 */
final class NotifyApplicationSubmitted
{
    public function handle(ApplicationSubmitted $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $owner = $event->application->offer->site->owner;

        if (! is_object($owner)) {
            return;
        }

        $dispatcher->handle(
            $owner,
            new ApplicationSubmittedNotification($event->application),
            new CommunicationContextData(
                purpose: 'affiliate-network.application_submitted',
                subjectType: $event->application->getMorphClass(),
                subjectId: (string) $event->application->getKey(),
                idempotencyKey: 'application-submitted:' . $event->application->getKey(),
            ),
        );
    }

    private static function dispatcher(): ?DispatchManagedNotificationAction
    {
        if (! config('affiliate-network.notifications.enabled', true)) {
            return null;
        }

        if (! class_exists(DispatchManagedNotificationAction::class)) {
            return null;
        }

        $bound = app()->bound(DispatchManagedNotificationAction::class);
        $loaded = isset(app()->getLoadedProviders()['AIArmada\Communications\CommunicationsServiceProvider']);

        if (! $bound && ! $loaded) {
            return null;
        }

        return app(DispatchManagedNotificationAction::class);
    }
}
