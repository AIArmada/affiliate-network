<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Exceptions;

use RuntimeException;

/**
 * A network feature needs the affiliates engine, which is not installed.
 *
 * The network package never requires aiarmada/affiliates; features that
 * need affiliate identity, the conversion ledger, or core programs resolve
 * them through the network seam (see Contracts/) with adapters provided
 * by the affiliates package. This exception means no adapter is bound.
 */
final class AffiliatesNotInstalled extends RuntimeException
{
    public static function forFeature(string $feature): self
    {
        return new self(sprintf(
            'aiarmada/affiliates is required for %s. Install it to enable this feature.',
            $feature,
        ));
    }
}
