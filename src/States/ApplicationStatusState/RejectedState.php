<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\States\ApplicationStatusState;

final class RejectedState extends ApplicationStatusState
{
    public static string $name = 'rejected';

    public function label(): string
    {
        return 'Rejected';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function isRejected(): bool
    {
        return true;
    }
}
