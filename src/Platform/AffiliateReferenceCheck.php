<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AffiliateReferenceCheck
{
    public static function exists(Model $model, string $affiliateId, int $connectionInstanceId): bool
    {
        if (spl_object_id($model->getConnection()) !== $connectionInstanceId) {
            throw new LogicException('Network affiliate removal checks require a shared database connection.');
        }

        // Merchant-side records may not be visible to the affiliate's owner.
        // Return existence only, restricted to the already authorized affiliate ID.
        return $model->newQueryWithoutScopes()->where('affiliate_id', $affiliateId)->exists();
    }
}
