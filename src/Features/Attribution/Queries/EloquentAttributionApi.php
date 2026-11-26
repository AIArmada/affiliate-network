<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Queries;

use AIArmada\AffiliateNetwork\Features\Attribution\Actions\DeleteOfferLinks;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RecordLinkCounters;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RecountLinkCounters;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferCouponRecord;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Carbon\CarbonImmutable;

final class EloquentAttributionApi implements AttributionApi
{
    public function __construct(
        private readonly ResolveOfferLinkQuery $links,
        private readonly ResolveOfferCouponQuery $coupons,
        private readonly LinkStatsQuery $stats,
        private readonly DeleteOfferLinks $deleteOfferLinks,
        private readonly RecordLinkCounters $recordLinkCounters,
        private readonly RecountLinkCounters $recountLinkCounters,
    ) {}

    public function findLinkByIdUnscoped(string $linkId): ?OfferLinkRecord
    {
        return $this->links->byIdUnscoped($linkId)?->toRecord();
    }

    public function resolveLinkBySlug(string $slug): ?OfferLinkRecord
    {
        return $this->links->bySlug($slug)?->toRecord();
    }

    public function findLinkBySlugUnscoped(string $slug, bool $requireActive = false): ?OfferLinkRecord
    {
        return $this->links->bySlugUnscoped($slug, $requireActive)?->toRecord();
    }

    public function resolveCouponForSite(string $siteId, string $code, ?CarbonImmutable $at = null): ?OfferCouponRecord
    {
        return $this->coupons->forSite($siteId, $code, $at)?->toRecord();
    }

    public function recordLinkCounters(string $linkId, int $revenueDeltaMinor, int $conversionDelta = 1): void
    {
        $this->recordLinkCounters->execute($linkId, $revenueDeltaMinor, $conversionDelta);
    }

    public function setLinkCounters(string $linkId, int $conversions, int $revenueMinor): void
    {
        $this->recountLinkCounters->execute($linkId, $conversions, $revenueMinor);
    }

    public function linkStats(string $linkId): array
    {
        return $this->stats->execute($linkId);
    }

    public function offerLinkIds(string $offerId): array
    {
        return AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function offerLinkTotals(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $totals = [];

        foreach ($offerIds as $offerId) {
            $totals[(string) $offerId] = ['clicks' => 0, 'conversions' => 0, 'revenue_minor' => 0];
        }

        $rows = AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereIn('offer_id', array_keys($totals))
            ->selectRaw('offer_id, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(revenue) as revenue_minor')
            ->groupBy('offer_id')
            ->get();

        foreach ($rows as $row) {
            /** @var array{offer_id: string, clicks: int|string, conversions: int|string, revenue_minor: int|string} $row */
            $offerId = (string) $row['offer_id'];
            $totals[$offerId] = [
                'clicks' => (int) $row['clicks'],
                'conversions' => (int) $row['conversions'],
                'revenue_minor' => (int) $row['revenue_minor'],
            ];
        }

        return $totals;
    }

    public function deleteForOffer(string $offerId): void
    {
        $this->deleteOfferLinks->execute($offerId);
    }

    public function networkLinkTotalsUnscoped(): array
    {
        return AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->toBase()
            ->selectRaw('currency, COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(conversions), 0) as conversions')
            ->groupBy('currency')
            ->get()
            ->map(static fn (object $row): array => [
                'currency' => $row->currency,
                'revenue' => (int) $row->revenue,
                'clicks' => (int) $row->clicks,
                'conversions' => (int) $row->conversions,
            ])
            ->all();
    }
}
