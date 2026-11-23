<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum LegStatus: string
{
    case Provisional = 'provisional';
    case Pending = 'pending';
    case Posted = 'posted';
    case Superseded = 'superseded';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'Provisional',
            self::Pending => 'Pending',
            self::Posted => 'Posted',
            self::Superseded => 'Superseded',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Provisional => 'warning',
            self::Pending => 'info',
            self::Posted => 'success',
            self::Superseded => 'gray',
            self::Reversed => 'danger',
        };
    }

    public function paysOut(): bool
    {
        return $this === self::Posted;
    }

    public function isPayableOrPending(): bool
    {
        return in_array($this, [self::Pending, self::Posted], true);
    }
}
