<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models\Concerns;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Trait for models that belong to an offer and need owner scoping through the offer→site chain.
 * Ensures creatives inherit owner context from their parent offer's site.
 */
trait ScopesByOfferOwner
{
    public static function bootScopesByOfferOwner(): void
    {
        if (! config('affiliate-network.owner.enabled', false)) {
            return;
        }

        static::addGlobalScope('owner_via_offer', function (Builder $builder): void {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $builder->getModel()::class),
            );

            $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
            $siteTable = config('affiliate-network.database.tables.sites', $prefix . 'sites');

            if ($owner === null) {
                $builder->whereHas('offer', function (Builder $query) use ($siteTable): void {
                    $query->whereHas('site', function (Builder $q) use ($siteTable): void {
                        $q->whereNull("{$siteTable}.owner_type")
                            ->whereNull("{$siteTable}.owner_id");
                    });
                });

                return;
            }

            $builder->whereHas('offer', function (Builder $query) use ($owner, $siteTable): void {
                $query->whereHas('site', function (Builder $q) use ($owner, $siteTable): void {
                    $q->where(function (Builder $scopedQuery) use ($owner, $siteTable): void {
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
        });

        static::creating(function ($model): void {
            if (! isset($model->offer_id)) {
                return;
            }

            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );

            // Fresh query — never trust a potentially stale preloaded relationship
            $offer = AffiliateOffer::withoutGlobalScope('owner_via_site')
                ->whereKey($model->offer_id)
                ->first();

            if ($offer === null) {
                throw new RuntimeException('Cannot create creative for an inaccessible or missing offer.');
            }

            $site = $offer->site;
            if ($site === null) {
                throw new RuntimeException('Cannot create creative: offer has no site.');
            }

            if ($owner === null) {
                if ($site->owner_type !== null || $site->owner_id !== null) {
                    throw new RuntimeException('Explicit global owner context is required for creatives linked to offers of owned sites.');
                }

                return;
            }

            if ($site->owner_type === null || $site->owner_id === null) {
                throw new RuntimeException('Explicit global owner context is required for creatives linked to offers of global sites.');
            }

            $siteOwner = OwnerContext::fromTypeAndId((string) $site->owner_type, (string) $site->owner_id);

            if ($siteOwner === null) {
                throw new RuntimeException('Site owner could not be resolved.');
            }

            if ($siteOwner::class !== $owner::class || (string) $siteOwner->getKey() !== (string) $owner->getKey()) {
                throw new RuntimeException('Cannot create creative for an offer owned by a different owner.');
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('offer_id')) {
                return;
            }

            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );

            // Fresh query — never trust a potentially stale preloaded relationship
            $offer = AffiliateOffer::withoutGlobalScope('owner_via_site')
                ->whereKey($model->offer_id)
                ->first();

            if ($offer === null) {
                throw new RuntimeException('Cannot assign creative to an inaccessible or missing offer.');
            }

            $site = $offer->site;
            if ($site === null) {
                throw new RuntimeException('Cannot assign creative: offer has no site.');
            }

            if ($owner === null) {
                if ($site->owner_type !== null || $site->owner_id !== null) {
                    throw new RuntimeException('Explicit global owner context is required for creatives linked to offers of owned sites.');
                }

                return;
            }

            if ($site->owner_type === null || $site->owner_id === null) {
                throw new RuntimeException('Explicit global owner context is required for creatives linked to offers of global sites.');
            }

            $siteOwner = OwnerContext::fromTypeAndId((string) $site->owner_type, (string) $site->owner_id);

            if ($siteOwner === null) {
                throw new RuntimeException('Site owner could not be resolved.');
            }

            if ($siteOwner::class !== $owner::class || (string) $siteOwner->getKey() !== (string) $owner->getKey()) {
                throw new RuntimeException('Cannot assign creative to an offer owned by a different owner.');
            }
        });
    }
}
