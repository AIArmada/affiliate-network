<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum SiteStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Suspended => 'Suspended',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Suspended => 'danger',
            self::Rejected => 'gray',
        };
    }
}
