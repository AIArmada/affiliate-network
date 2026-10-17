<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

final class AffiliateProfileRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Complete affiliate registration before writing affiliate-owned network records while owner scoping is enabled.');
    }
}
