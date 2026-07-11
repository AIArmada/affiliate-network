<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\States\ApplicationStatusState;

final class RevokedState extends ApplicationStatusState
{
    public static string $name = 'revoked';

    public function label(): string
    {
        return 'Revoked';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function isRevoked(): bool
    {
        return true;
    }
}
