<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum PostbackEventType: string
{
    case ConversionRecorded = 'conversion.recorded';
    case ConversionLocked = 'conversion.locked';
    case ConversionReversed = 'conversion.reversed';
    case ConversionAdjusted = 'conversion.adjusted';

    public function label(): string
    {
        return match ($this) {
            self::ConversionRecorded => 'Conversion recorded',
            self::ConversionLocked => 'Conversion locked',
            self::ConversionReversed => 'Conversion reversed',
            self::ConversionAdjusted => 'Conversion adjusted',
        };
    }
}
