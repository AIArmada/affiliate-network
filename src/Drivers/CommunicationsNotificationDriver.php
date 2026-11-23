<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers;

use AIArmada\AffiliateNetwork\Contracts\NotificationDispatcher;
use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use AIArmada\AffiliateNetwork\Notifications\ApplicationApprovedNotification;
use AIArmada\AffiliateNetwork\Notifications\ApplicationSubmittedNotification;
use AIArmada\AffiliateNetwork\Notifications\NetworkConversionNotification;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CommunicationsNotificationDriver implements NotificationDispatcher
{
    public function dispatchToUser(
        string $userId,
        ?string $email,
        string $purpose,
        string $subjectType,
        string $subjectId,
        string $idempotencyKey,
        NotificationPayload $payload,
    ): void {
        $dispatcher = $this->dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $user = $this->userById($userId);

        if ($user === null && is_string($email) && $email !== '') {
            $user = $this->userByEmail($email);
        }

        if (! $user instanceof Model) {
            return;
        }

        $dispatcher->handle(
            $user,
            $this->notificationFor($purpose, $payload),
            new CommunicationContextData(
                purpose: $purpose,
                subjectType: $subjectType,
                subjectId: $subjectId,
                idempotencyKey: $idempotencyKey,
            ),
        );
    }

    public function dispatchToOwner(
        ?string $ownerType,
        string | int | null $ownerId,
        string $purpose,
        string $subjectType,
        string $subjectId,
        string $idempotencyKey,
        NotificationPayload $payload,
    ): void {
        $dispatcher = $this->dispatcher();

        if ($dispatcher === null) {
            return;
        }

        try {
            $owner = OwnerContext::fromTypeAndIdOrFail($ownerType, $ownerId);
        } catch (InvalidArgumentException | ModelNotFoundException) {
            return;
        }

        if (! $owner instanceof Model) {
            return;
        }

        $dispatcher->handle(
            $owner,
            $this->notificationFor($purpose, $payload),
            new CommunicationContextData(
                purpose: $purpose,
                subjectType: $subjectType,
                subjectId: $subjectId,
                idempotencyKey: $idempotencyKey,
            ),
        );
    }

    private function notificationFor(string $purpose, NotificationPayload $payload): Notification
    {
        if (str_contains($purpose, 'approved')) {
            return new ApplicationApprovedNotification($payload);
        }

        if (str_contains($purpose, 'conversion')) {
            return new NetworkConversionNotification($payload);
        }

        return new ApplicationSubmittedNotification($payload);
    }

    private function userById(string $userId): ?Model
    {
        $model = $this->userModelClass();

        if ($model === null) {
            return null;
        }

        $instance = new $model;

        if ($instance->getKeyType() === 'int' && ! ctype_digit($userId)) {
            return null;
        }

        if (in_array(HasUuids::class, class_uses_recursive($model), true) && ! Str::isUuid($userId)) {
            return null;
        }

        $user = $instance->newQuery()->whereKey($userId)->first();

        return $user instanceof Model ? $user : null;
    }

    private function userByEmail(string $email): ?Model
    {
        $model = $this->userModelClass();

        if ($model === null) {
            return null;
        }

        $user = (new $model)->newQuery()->where('email', $email)->first();

        return $user instanceof Model ? $user : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function userModelClass(): ?string
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model;
    }

    private function dispatcher(): ?DispatchManagedNotificationAction
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
