<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum SiteSyncStatus: string
{
    case Never = 'never';
    case Ok = 'ok';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::Ok => 'OK',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Never => 'gray',
            self::Ok => 'success',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }
}
