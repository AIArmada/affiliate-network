<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class AffiliateOfferEligibility
{
    public function isEligible(AffiliateOffer $offer, bool $requirePublicVisibility = true): bool
    {
        if ($offer->status !== OfferStatus::Published) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($offer->starts_at !== null && $now->lessThan($offer->starts_at)) {
            return false;
        }

        if ($offer->ends_at !== null && $now->greaterThan($offer->ends_at)) {
            return false;
        }

        $site = $offer->relationLoaded('site')
            ? $offer->site
            : $offer->site()->withoutGlobalScopes()->first();

        if ($site === null || ! $site->isVerified()) {
            return false;
        }

        if ($requirePublicVisibility && $offer->visibility !== OfferVisibility::Public) {
            return false;
        }

        return true;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToQuery(Builder $query, bool $requirePublicVisibility = true): Builder
    {
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        $siteTable = config('affiliate-network.database.tables.sites', $prefix . 'sites');

        $now = CarbonImmutable::now();

        $query
            ->where('status', OfferStatus::Published)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereIn('site_id', function ($q) use ($siteTable): void {
                $q->select('id')
                    ->from($siteTable)
                    ->where('status', AffiliateSite::STATUS_VERIFIED);
            });

        if ($requirePublicVisibility) {
            $query->where('visibility', OfferVisibility::Public);
        }

        return $query;
    }
}
