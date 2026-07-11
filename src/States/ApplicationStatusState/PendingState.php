<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\States\ApplicationStatusState;

final class PendingState extends ApplicationStatusState
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending';
    }

    public function color(): string
    {
        return 'warning';
    }

    public function isPending(): bool
    {
        return true;
    }
}
