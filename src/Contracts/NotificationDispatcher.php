<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\NotificationPayload;

interface NotificationDispatcher
{
    public function dispatchToUser(
        string $userId,
        ?string $email,
        string $purpose,
        string $subjectType,
        string $subjectId,
        string $idempotencyKey,
        NotificationPayload $payload,
    ): void;

    public function dispatchToOwner(
        ?string $ownerType,
        string | int | null $ownerId,
        string $purpose,
        string $subjectType,
        string $subjectId,
        string $idempotencyKey,
        NotificationPayload $payload,
    ): void;
}
