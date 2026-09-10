<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Scope a model through a belongs-to owner path while delegating owner
 * predicate and context semantics to commerce-support's OwnerScope.
 *
 * Implementations name the relation path (for example, `site` or
 * `offer.site`) and the config key owned by the related model's package.
 */
trait ScopesByBelongsToOwner
{
    /**
     * Return the dot-notated belongs-to path that reaches the owner-bearing model.
     */
    abstract protected static function ownerViaRelation(): string;

    /**
     * Return the config key for the owner-bearing model's OwnerScopeConfig.
     */
    abstract protected static function ownerTableConfigKey(): string;

    public static function bootScopesByBelongsToOwner(): void
    {
        static::addGlobalScope(ScopesByBelongsToOwner::class, function (Builder $builder): void {
            $config = OwnerScopeConfig::fromConfig(static::ownerTableConfigKey());

            if (! $config->enabled) {
                return;
            }

            $builder->whereHas(static::ownerViaRelation(), function (Builder $query) use ($config): void {
                $related = $query->getModel();

                $query->withoutGlobalScope(OwnerScope::class);
                (new OwnerScope($config))->apply($query, $related);
            });
        });

        static::creating(function (Model $model): void {
            self::assertOwnerRelationAccessible($model);
        });

        static::updating(function (Model $model): void {
            if (self::ownerRelationForeignKey($model) !== null) {
                self::assertOwnerRelationAccessible($model);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutOwnerScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ScopesByBelongsToOwner::class);
    }

    private static function assertOwnerRelationAccessible(Model $model): void
    {
        $config = OwnerScopeConfig::fromConfig(static::ownerTableConfigKey());

        if (! $config->enabled) {
            return;
        }

        $segments = explode('.', static::ownerViaRelation());
        $foreignKey = self::ownerRelationForeignKey($model);

        if ($foreignKey === null || $model->getAttribute($foreignKey) === null) {
            return;
        }

        $current = $model;

        foreach ($segments as $index => $segment) {
            $relation = $current->{$segment}();
            $query = $relation->getQuery()->withoutGlobalScope(OwnerScope::class);

            if ($index === array_key_last($segments)) {
                (new OwnerScope($config))->apply($query, $query->getModel());
            }

            $related = $query->first();

            if (! $related instanceof Model) {
                throw new RuntimeException(sprintf(
                    'Cannot create or update %s for an inaccessible or missing owner relation.',
                    $model::class,
                ));
            }

            $current = $related;
        }
    }

    private static function ownerRelationForeignKey(Model $model): ?string
    {
        $firstSegment = explode('.', static::ownerViaRelation(), 2)[0];
        $relation = $model->{$firstSegment}();

        if (! $relation instanceof BelongsTo) {
            throw new RuntimeException(sprintf(
                '%s::ownerViaRelation() must begin with a belongs-to relation.',
                static::class,
            ));
        }

        $foreignKey = $relation->getForeignKeyName();

        return $model->getAttribute($foreignKey) === null || $model->isDirty($foreignKey)
            ? $foreignKey
            : null;
    }
}
