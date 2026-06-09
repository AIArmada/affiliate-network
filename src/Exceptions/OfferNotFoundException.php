<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class OfferNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("Affiliate offer [{$id}] not found.");
    }

    public static function withCode(string $code): self
    {
        return new self("Affiliate offer with code [{$code}] not found.");
    }
}
