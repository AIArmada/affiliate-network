<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
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
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $builder->getModel()::class),
            );

            $affiliatesTable = config('affiliates.database.tables.affiliates', 'affiliate_affiliates');

            if ($owner === null) {
                $builder->whereHas('affiliate', function (Builder $query) use ($affiliatesTable): void {
                    $query->whereNull("{$affiliatesTable}.owner_type")
                        ->whereNull("{$affiliatesTable}.owner_id");
                });

                return;
            }

            $builder->whereHas('affiliate', function (Builder $query) use ($owner, $affiliatesTable): void {
                $query->where(function (Builder $scopedQuery) use ($owner, $affiliatesTable): void {
                    $scopedQuery->where("{$affiliatesTable}.owner_type", $owner->getMorphClass())
                        ->where("{$affiliatesTable}.owner_id", $owner->getKey());

                    if (config('affiliate-network.owner.include_global', false)) {
                        $scopedQuery->orWhere(function (Builder $globalQuery) use ($affiliatesTable): void {
                            $globalQuery->whereNull("{$affiliatesTable}.owner_type")
                                ->whereNull("{$affiliatesTable}.owner_id");
                        });
                    }
                });
            });
        });

        static::creating(function ($model): void {
            if (! isset($model->affiliate_id)) {
                return;
            }

            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );

            $affiliate = $model->affiliate;
            if ($affiliate === null) {
                throw new RuntimeException('Cannot create record for an inaccessible or missing affiliate.');
            }

            if ($owner === null) {
                if ($affiliate->owner_type !== null || $affiliate->owner_id !== null) {
                    throw new RuntimeException('Explicit global owner context is required for records linked to owned affiliates.');
                }

                return;
            }

            if ($affiliate->owner_type === null || $affiliate->owner_id === null) {
                throw new RuntimeException('Explicit global owner context is required for records linked to global affiliates.');
            }

            $affiliateOwner = OwnerContext::fromTypeAndId((string) $affiliate->owner_type, (string) $affiliate->owner_id);

            if ($affiliateOwner === null) {
                throw new RuntimeException('Affiliate owner could not be resolved.');
            }

            if ($affiliateOwner::class !== $owner::class || (string) $affiliateOwner->getKey() !== (string) $owner->getKey()) {
                throw new RuntimeException('Cannot create record for an affiliate owned by a different owner.');
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('affiliate_id')) {
                return;
            }

            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );

            $affiliate = $model->affiliate;
            if ($affiliate === null) {
                throw new RuntimeException('Cannot assign record to an inaccessible or missing affiliate.');
            }

            if ($owner === null) {
                if ($affiliate->owner_type !== null || $affiliate->owner_id !== null) {
                    throw new RuntimeException('Explicit global owner context is required for records linked to owned affiliates.');
                }

                return;
            }

            if ($affiliate->owner_type === null || $affiliate->owner_id === null) {
                throw new RuntimeException('Explicit global owner context is required for records linked to global affiliates.');
            }

            $affiliateOwner = OwnerContext::fromTypeAndId((string) $affiliate->owner_type, (string) $affiliate->owner_id);

            if ($affiliateOwner === null) {
                throw new RuntimeException('Affiliate owner could not be resolved.');
            }

            if ($affiliateOwner::class !== $owner::class || (string) $affiliateOwner->getKey() !== (string) $owner->getKey()) {
                throw new RuntimeException('Cannot assign record to an affiliate owned by a different owner.');
            }
        });
    }
}
