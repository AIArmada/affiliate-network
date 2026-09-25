<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Notifications\ApplicationApprovedNotification;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;

/**
 * Notifies the applicant when their application is approved.
 *
 * The applicant resolves through the host user model; ids that are
 * not users receive nothing. Managed notifications via the
 * communications package when installed; silent otherwise.
 */
final class NotifyApplicationApproved
{
    public function handle(ApplicationApproved $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $applicant = self::applicantOf($event->application->affiliate_id);

        if ($applicant === null) {
            return;
        }

        $dispatcher->handle(
            $applicant,
            new ApplicationApprovedNotification($event->application),
            new CommunicationContextData(
                purpose: 'affiliate-network.application_approved',
                subjectType: $event->application->getMorphClass(),
                subjectId: (string) $event->application->getKey(),
                idempotencyKey: 'application-approved:' . $event->application->getKey(),
            ),
        );
    }

    private static function applicantOf(mixed $affiliateId): ?object
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($affiliateId) || ! is_string($userModel) || ! class_exists($userModel)) {
            return null;
        }

        $user = $userModel::query()->whereKey($affiliateId)->first();

        return is_object($user) ? $user : null;
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
