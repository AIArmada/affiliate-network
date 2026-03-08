<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models\Concerns;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Trait for models that belong to an affiliate and need owner scoping through the affiliate relationship.
 * Ensures applications, links, and related models inherit owner context from their parent affiliate.
 */
trait ScopesByAffiliateOwner
{
    public static function bootScopesByAffiliateOwner(): void
    {
        if (! config('affiliate-network.owner.enabled', false)) {
            return;
        }

        static::addGlobalScope('owner_via_affiliate', function (Builder $builder): void {
            $resolver = app(OwnerResolverInterface::class);
            $owner = $resolver->resolve();

            if ($owner === null) {
                return;
            }

            $affiliatesTable = config('affiliates.database.tables.affiliates', 'affiliate_affiliates');

            $builder->whereHas('affiliate', function (Builder $query) use ($owner, $affiliatesTable): void {
                $query->where("{$affiliatesTable}.owner_type", $owner->getMorphClass())
                    ->where("{$affiliatesTable}.owner_id", $owner->getKey());

                if (config('affiliate-network.owner.include_global', false)) {
                    $query->orWhereNull("{$affiliatesTable}.owner_id");
                }
            });
        });

        static::creating(function ($model): void {
            if (! isset($model->affiliate_id)) {
                return;
            }

            $resolver = app(OwnerResolverInterface::class);
            $owner = $resolver->resolve();

            if ($owner === null) {
                return;
            }

            $affiliate = $model->affiliate;
            if ($affiliate === null) {
                return;
            }

            if ($affiliate->owner_id !== null && $affiliate->owner_id !== $owner->getKey()) {
                throw new RuntimeException('Cannot create record for an affiliate owned by a different owner.');
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('affiliate_id')) {
                return;
            }

            $resolver = app(OwnerResolverInterface::class);
            $owner = $resolver->resolve();

            if ($owner === null) {
                return;
            }

            $affiliate = $model->affiliate;
            if ($affiliate === null) {
                return;
            }

            if ($affiliate->owner_id !== null && $affiliate->owner_id !== $owner->getKey()) {
                throw new RuntimeException('Cannot assign record to an affiliate owned by a different owner.');
            }
        });
    }
}
