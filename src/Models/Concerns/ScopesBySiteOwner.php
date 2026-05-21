<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Trait for models that belong to a site and need owner scoping through the site relationship.
 * Ensures offers and related models inherit owner context from their parent site.
 */
trait ScopesBySiteOwner
{
    public static function bootScopesBySiteOwner(): void
    {
        if (! config('affiliate-network.owner.enabled', false)) {
            return;
        }

        static::addGlobalScope('owner_via_site', function (Builder $builder): void {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $builder->getModel()::class),
            );

            $siteTable = config('affiliate-network.database.tables.sites', 'affiliate_network_sites');

            if ($owner === null) {
                $builder->whereHas('site', function (Builder $query) use ($siteTable): void {
                    $query->whereNull("{$siteTable}.owner_type")
                        ->whereNull("{$siteTable}.owner_id");
                });

                return;
            }

            $builder->whereHas('site', function (Builder $query) use ($owner, $siteTable): void {
                $query->where(function (Builder $scopedQuery) use ($owner, $siteTable): void {
                    $scopedQuery->where("{$siteTable}.owner_type", $owner->getMorphClass())
                        ->where("{$siteTable}.owner_id", $owner->getKey());

                    if (config('affiliate-network.owner.include_global', false)) {
                        $scopedQuery->orWhere(function (Builder $globalQuery) use ($siteTable): void {
                            $globalQuery->whereNull("{$siteTable}.owner_type")
                                ->whereNull("{$siteTable}.owner_id");
                        });
                    }
                });
            });
        });

        static::creating(function ($model): void {
            if (! isset($model->site_id)) {
                return;
            }

            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );

            $site = $model->site;
            if ($site === null) {
                throw new RuntimeException('Cannot create record for an inaccessible or missing site.');
            }

            if ($owner === null) {
                if ($site->owner_type !== null || $site->owner_id !== null) {
                    throw new RuntimeException('Explicit global owner context is required for records linked to owned sites.');
                }

                return;
            }

            if ($site->owner_type === null || $site->owner_id === null) {
                throw new RuntimeException('Explicit global owner context is required for records linked to global sites.');
            }

            $siteOwner = OwnerContext::fromTypeAndId((string) $site->owner_type, (string) $site->owner_id);

            if ($siteOwner === null) {
                throw new RuntimeException('Site owner could not be resolved.');
            }

            if ($siteOwner::class !== $owner::class || (string) $siteOwner->getKey() !== (string) $owner->getKey()) {
                throw new RuntimeException('Cannot create record for a site owned by a different owner.');
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('site_id')) {
                return;
            }

            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );

            $site = $model->site;
            if ($site === null) {
                throw new RuntimeException('Cannot assign record to an inaccessible or missing site.');
            }

            if ($owner === null) {
                if ($site->owner_type !== null || $site->owner_id !== null) {
                    throw new RuntimeException('Explicit global owner context is required for records linked to owned sites.');
                }

                return;
            }

            if ($site->owner_type === null || $site->owner_id === null) {
                throw new RuntimeException('Explicit global owner context is required for records linked to global sites.');
            }

            $siteOwner = OwnerContext::fromTypeAndId((string) $site->owner_type, (string) $site->owner_id);

            if ($siteOwner === null) {
                throw new RuntimeException('Site owner could not be resolved.');
            }

            if ($siteOwner::class !== $owner::class || (string) $siteOwner->getKey() !== (string) $owner->getKey()) {
                throw new RuntimeException('Cannot assign record to a site owned by a different owner.');
            }
        });
    }
}
