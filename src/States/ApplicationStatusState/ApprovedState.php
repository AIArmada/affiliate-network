<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\States\ApplicationStatusState;

final class ApprovedState extends ApplicationStatusState
{
    public static string $name = 'approved';

    public function label(): string
    {
        return 'Approved';
    }

    public function color(): string
    {
        return 'success';
    }

    public function isApproved(): bool
    {
        return true;
    }
}
