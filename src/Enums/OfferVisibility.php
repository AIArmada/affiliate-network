<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Enums;

enum OfferVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Unlisted = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Unlisted => 'Unlisted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'gray',
            self::Unlisted => 'warning',
        };
    }
}
