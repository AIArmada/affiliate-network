<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class UnauthorizedRedirectTargetException extends RuntimeException
{
    public static function forHost(string $host): self
    {
        return new self("Redirect target host [{$host}] is not allowed.");
    }
}
