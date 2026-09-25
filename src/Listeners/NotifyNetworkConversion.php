<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Notifications\NetworkConversionNotification;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;

/**
 * Notifies the creator when their link earns a posted payout.
 *
 * Counters-only and provisional conversions notify nothing — only
 * posted legs with a payable amount reach the creator.
 */
final class NotifyNetworkConversion
{
    public function handle(NetworkConversionRecorded $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $leg = $event->leg;

        if ($leg === null || ! $leg->paysOut() || (int) $leg->payout_minor <= 0) {
            return;
        }

        $applicant = self::applicantOf($leg->affiliate_id);

        if ($applicant === null) {
            return;
        }

        $dispatcher->handle(
            $applicant,
            new NetworkConversionNotification($leg),
            new CommunicationContextData(
                purpose: 'affiliate-network.conversion_recorded',
                subjectType: $leg->getMorphClass(),
                subjectId: (string) $leg->getKey(),
                idempotencyKey: 'network-conversion:' . $leg->getKey(),
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
