<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class SiteVerificationFailedException extends RuntimeException
{
    public static function methodFailed(string $method, string $reason): self
    {
        return new self("Site verification via [{$method}] failed: {$reason}");
    }

    public static function noMethodsRemaining(): self
    {
        return new self('All site verification methods have been exhausted.');
    }
}
