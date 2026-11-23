<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum PostbackDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }
}
