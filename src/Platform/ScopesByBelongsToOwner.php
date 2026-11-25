<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use RuntimeException;
use Throwable;

trait ScopesByBelongsToOwner
{
    abstract protected static function ownerViaRelation(): string;

    abstract protected static function ownerTableConfigKey(): string;

    protected static function ownerFallbackRelation(): ?string
    {
        return null;
    }

    public static function bootScopesByBelongsToOwner(): void
    {
        static::addGlobalScope(ScopesByBelongsToOwner::class, function (Builder $builder): void {
            $config = OwnerScopeConfigFactory::fromConfig(static::ownerTableConfigKey());

            if (! $config->enabled) {
                return;
            }

            $relation = static::effectiveOwnerRelation($builder->getModel());

            if ($relation === null) {
                return;
            }

            $builder->whereHas($relation, function (Builder $query) use ($config): void {
                $related = $query->getModel();

                $query->withoutGlobalScope(OwnerScope::class);
                (new OwnerScope($config))->apply($query, $related);
            });
        });

        static::creating(function (Model $model): void {
            self::assertOwnerRelationAccessible($model);
        });

        static::updating(function (Model $model): void {
            self::assertOwnerRelationAccessible($model);
        });

        static::deleting(function (Model $model): void {
            self::assertOwnerRelationAccessible($model);
        });
    }

    protected static function effectiveOwnerRelation(Model $model): ?string
    {
        $config = OwnerScopeConfigFactory::fromConfig(static::ownerTableConfigKey());

        if (! $config->enabled) {
            return null;
        }

        $primary = static::ownerViaRelation();

        if (self::relationUsesHasOwner($model, $primary)) {
            return $primary;
        }

        $fallback = static::ownerFallbackRelation();

        if ($fallback !== null && self::relationUsesHasOwner($model, $fallback)) {
            return $fallback;
        }

        return null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutOwnerScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ScopesByBelongsToOwner::class);
    }

    private static function relationUsesHasOwner(Model $model, string $relation): bool
    {
        $related = self::relatedModelThrough($model, $relation);

        if ($related === null) {
            return false;
        }

        return in_array(HasOwner::class, class_uses_recursive($related), true);
    }

    private static function relatedModelThrough(Model $model, string $relation): ?Model
    {
        $current = $model;

        foreach (explode('.', $relation) as $segment) {
            try {
                $instance = $current->{$segment}();
            } catch (Throwable) {
                return null;
            }

            if (! $instance instanceof Relation) {
                return null;
            }

            $current = $instance->getRelated();
        }

        return $current;
    }

    private static function assertOwnerRelationAccessible(Model $model): void
    {
        $config = OwnerScopeConfigFactory::fromConfig(static::ownerTableConfigKey());

        if (! $config->enabled) {
            return;
        }

        $relation = static::effectiveOwnerRelation($model);

        if ($relation === null) {
            return;
        }

        $segments = explode('.', $relation);
        $first = $model->{$segments[0]}();

        if (! $first instanceof BelongsTo) {
            throw new RuntimeException(sprintf(
                '%s::ownerViaRelation() must begin with a belongs-to relation.',
                static::class,
            ));
        }

        if ($model->getAttribute($first->getForeignKeyName()) === null) {
            return;
        }

        $current = $model;

        foreach ($segments as $index => $segment) {
            $instance = $current->{$segment}();

            if (! $instance instanceof Relation) {
                throw new RuntimeException(sprintf(
                    'Cannot resolve owner relation segment [%s] on %s.',
                    $segment,
                    $current::class,
                ));
            }

            $query = $instance->getQuery()->withoutGlobalScope(OwnerScope::class);

            if ($index === array_key_last($segments)) {
                (new OwnerScope($config))->apply($query, $query->getModel());
            } else {
                $query->withoutGlobalScope(self::class);
            }

            $related = $query->first();

            if (! $related instanceof Model) {
                throw new RuntimeException(sprintf(
                    'Cannot create, update, or delete %s without an accessible owner relation.',
                    $model::class,
                ));
            }

            $current = $related;
        }
    }
}
