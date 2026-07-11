<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class IneligibleOfferException extends RuntimeException
{
    public static function forOffer(string $offerId): self
    {
        return new self("Affiliate offer [{$offerId}] is not eligible.");
    }
}
