<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum OfferSource: string
{
    case Mirrored = 'mirrored';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Mirrored => 'Mirrored',
            self::Manual => 'Manual',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mirrored => 'info',
            self::Manual => 'gray',
        };
    }
}
