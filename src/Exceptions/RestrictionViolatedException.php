<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class RestrictionViolatedException extends RuntimeException
{
    public static function forOffer(string $offerId, string $reason): self
    {
        return new self("Offer [{$offerId}] restriction violated: {$reason}.");
    }
}
