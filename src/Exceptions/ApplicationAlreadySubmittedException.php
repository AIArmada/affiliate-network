<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class ApplicationAlreadySubmittedException extends RuntimeException
{
    public static function forOffer(string $offerId): self
    {
        return new self("An application for offer [{$offerId}] has already been submitted.");
    }
}
