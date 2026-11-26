<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Data\OwnerTableReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RuntimeException;
use Throwable;

trait ScopesByBelongsToOwner
{
    abstract protected static function ownerViaRelation(): string;

    abstract protected static function ownerTableConfigKey(): string;

    protected static function ownerViaAffiliateIdentity(): bool
    {
        return false;
    }

    public static function bootScopesByBelongsToOwner(): void
    {
        static::addGlobalScope(ScopesByBelongsToOwner::class, function (Builder $builder): void {
            $config = OwnerScopeConfigFactory::fromConfig(static::ownerTableConfigKey());

            if (! $config->enabled) {
                return;
            }

            $model = $builder->getModel();
            $source = self::affiliateOwnerTable();

            if ($source !== null) {
                $query = self::affiliateOwnerQuery($model, $source, $config);
                $query->selectRaw(self::affiliateKeyExpression($model, $source));
                $builder->whereIn($model->qualifyColumn('affiliate_id'), $query);

                return;
            }

            $relation = static::effectiveOwnerRelation($model);

            if ($relation === null) {
                throw new RuntimeException(sprintf('No owner relation is configured for %s.', $builder->getModel()::class));
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

    private static function affiliateOwnerTable(): ?OwnerTableReference
    {
        return static::ownerViaAffiliateIdentity() ? app(AffiliateIdentityResolver::class)->ownerTable() : null;
    }

    private static function affiliateKeyExpression(Model $model, OwnerTableReference $source): string
    {
        $connection = $model->getConnection();
        $key = $connection->getQueryGrammar()->wrap($source->keyColumn);

        return match ($connection->getDriverName()) {
            'pgsql', 'sqlite' => 'CAST(' . $key . ' AS TEXT)',
            'mysql', 'mariadb' => 'CAST(' . $key . ' AS CHAR)',
            'sqlsrv' => 'CAST(' . $key . ' AS NVARCHAR(255))',
            default => $key,
        };
    }

    private static function affiliateOwnerQuery(Model $model, OwnerTableReference $source, OwnerScopeConfig $config): QueryBuilder
    {
        $connection = $model->getConnection();

        if (spl_object_id($connection) !== $source->connectionIdentity) {
            throw new RuntimeException('Affiliate ownership requires a shared database connection.');
        }

        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerQuery::applyToQueryBuilder(
            $connection->table($source->table),
            $owner,
            ! OwnerScopeOverride::suppressIncludeGlobal() && $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    private static function assertAffiliateOwnerAccessible(Model $model, OwnerTableReference $source, OwnerScopeConfig $config): void
    {
        $ids = [$model->getAttribute('affiliate_id')];

        if ($model->exists) {
            $ids[] = $model->getRawOriginal('affiliate_id');
        }

        if (in_array(null, $ids, true) || in_array('', $ids, true)) {
            throw new RuntimeException('An affiliate owner reference is required.');
        }

        $ids = array_values(array_unique($ids));
        $query = self::affiliateOwnerQuery($model, $source, $config);
        $query->whereIn($model->getConnection()->raw(self::affiliateKeyExpression($model, $source)), $ids);

        if ($query->count() !== count($ids)) {
            throw new RuntimeException(sprintf('Cannot create, update, or delete %s without an accessible owner relation.', $model::class));
        }
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

        $source = self::affiliateOwnerTable();

        if ($source !== null) {
            self::assertAffiliateOwnerAccessible($model, $source, $config);

            return;
        }

        $relation = static::effectiveOwnerRelation($model);

        if ($relation === null) {
            throw new RuntimeException(sprintf('No owner relation is configured for %s.', $model::class));
        }

        $segments = explode('.', $relation);
        $first = $model->{$segments[0]}();

        if (! $first instanceof BelongsTo) {
            throw new RuntimeException(sprintf(
                '%s::ownerViaRelation() must begin with a belongs-to relation.',
                static::class,
            ));
        }

        $foreignKey = $first->getForeignKeyName();

        if ($model->exists && $model->isDirty($foreignKey)) {
            $original = $model->withoutRelations();
            $original->setRawAttributes($model->getRawOriginal(), true);
            self::assertOwnerRelationAccessible($original);
        }

        if ($model->getAttribute($foreignKey) === null) {
            throw new RuntimeException(sprintf('An owner relation is required for %s.', $model::class));
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
