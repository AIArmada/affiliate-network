<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class CapExceededException extends RuntimeException
{
    public static function forOffer(string $offerId, string $cap): self
    {
        return new self("Offer [{$offerId}] exceeded its {$cap} conversion cap.");
    }
}
