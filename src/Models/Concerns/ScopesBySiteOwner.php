<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models\Concerns;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
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
            $resolver = app(OwnerResolverInterface::class);
            $owner = $resolver->resolve();

            if ($owner === null) {
                return;
            }

            $model = $builder->getModel();
            $siteTable = config('affiliate-network.database.tables.sites', 'affiliate_network_sites');

            $builder->whereHas('site', function (Builder $query) use ($owner, $siteTable): void {
                $query->where("{$siteTable}.owner_type", $owner->getMorphClass())
                    ->where("{$siteTable}.owner_id", $owner->getKey());

                if (config('affiliate-network.owner.include_global', false)) {
                    $query->orWhereNull("{$siteTable}.owner_id");
                }
            });
        });

        static::creating(function ($model): void {
            if (! isset($model->site_id)) {
                return;
            }

            $resolver = app(OwnerResolverInterface::class);
            $owner = $resolver->resolve();

            if ($owner === null) {
                return;
            }

            $site = $model->site;
            if ($site === null) {
                return;
            }

            if ($site->owner_id !== null && $site->owner_id !== $owner->getKey()) {
                throw new RuntimeException('Cannot create record for a site owned by a different owner.');
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('site_id')) {
                return;
            }

            $resolver = app(OwnerResolverInterface::class);
            $owner = $resolver->resolve();

            if ($owner === null) {
                return;
            }

            $site = $model->site;
            if ($site === null) {
                return;
            }

            if ($site->owner_id !== null && $site->owner_id !== $owner->getKey()) {
                throw new RuntimeException('Cannot assign record to a site owned by a different owner.');
            }
        });
    }
}
