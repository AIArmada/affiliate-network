<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Enums;

enum LegStatus: string
{
    case Provisional = 'provisional';
    case Posted = 'posted';
    case Superseded = 'superseded';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'Provisional',
            self::Posted => 'Posted',
            self::Superseded => 'Superseded',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Provisional => 'warning',
            self::Posted => 'success',
            self::Superseded => 'gray',
            self::Reversed => 'danger',
        };
    }

    public function paysOut(): bool
    {
        return $this === self::Posted;
    }
}
