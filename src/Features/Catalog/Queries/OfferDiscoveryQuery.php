<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Queries;

use AIArmada\AffiliateNetwork\Data\OfferDiscoveryFilter;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use Illuminate\Database\Eloquent\Builder;

final class OfferDiscoveryQuery
{
    public function __construct(
        private readonly EvaluateOfferRestrictions $restrictions,
        private readonly OfferMetricsQuery $metrics,
    ) {}

    /**
     * @return list<OfferRecord>
     */
    public function execute(OfferDiscoveryFilter $filter): array
    {
        $query = AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->where('status', OfferStatus::Published)
            ->where('visibility', OfferVisibility::Public)
            ->whereSiteVerified();

        if ($filter->categoryId !== null && $filter->categoryId !== '') {
            $query->whereIn('category_id', $this->descendantCategoryIds($filter->categoryId));
        }

        if ($filter->search !== null && mb_trim($filter->search) !== '') {
            $term = '%' . mb_trim($filter->search) . '%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        if ($filter->currency !== null && $filter->currency !== '') {
            $query->where('currency', mb_strtoupper($filter->currency));
        }

        if ($filter->minRateBp !== null) {
            $query->where(function (Builder $builder) use ($filter): void {
                $builder->where('rate_base_bp', '>=', (int) $filter->minRateBp)
                    ->orWhereNotNull('rate_fixed_minor');
            });
        }

        if ($filter->requiresApproval !== null) {
            $query->where('requires_approval', $filter->requiresApproval);
        }

        if ($filter->featured !== null) {
            $query->where('is_featured', $filter->featured);
        }

        if ($filter->sort === 'highest_rate') {
            $query->orderByDesc('rate_base_bp');
        } elseif ($filter->sort === 'newest') {
            $query->orderByDesc('published_at');
        } else {
            $query->orderByDesc('is_featured')->orderByDesc('published_at');
        }

        $offers = $query
            ->limit(max(1, $filter->perPage) * 5)
            ->get()
            ->map(static fn (AffiliateOffer $offer): OfferRecord => $offer->toRecord())
            ->all();

        $context = new RestrictionContext(
            country: $filter->country,
            channel: $filter->channel,
            device: $filter->device,
        );

        $offers = array_values(array_filter(
            $offers,
            fn (OfferRecord $offer): bool => $this->restrictions->evaluate($offer, $context) === null,
        ));

        if (in_array($filter->sort, ['epc', 'conversion_rate'], true)) {
            $offers = $this->sortByMetric($offers, $filter->sort);
        }

        return array_slice($offers, 0, max(1, $filter->perPage));
    }

    /**
     * @return list<string>
     */
    private function descendantCategoryIds(string $categoryId): array
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while (count($frontier) > 0) {
            $children = AffiliateOfferCategory::query()
                ->withoutGlobalScopes()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            $new = array_values(array_diff($children, $ids));

            if (count($new) === 0) {
                break;
            }

            $ids = [...$ids, ...$new];
            $frontier = $new;
        }

        return $ids;
    }

    /**
     * @param  list<OfferRecord>  $offers
     * @return list<OfferRecord>
     */
    private function sortByMetric(array $offers, string $sort): array
    {
        $metrics = $this->metrics->batch($offers);

        usort($offers, static function (OfferRecord $a, OfferRecord $b) use ($metrics, $sort): int {
            $left = $metrics[$a->id] ?? null;
            $right = $metrics[$b->id] ?? null;

            $leftValue = $sort === 'epc'
                ? (float) ($left->epcMinor ?? 0.0)
                : (float) ($left->conversionRate ?? 0.0);
            $rightValue = $sort === 'epc'
                ? (float) ($right->epcMinor ?? 0.0)
                : (float) ($right->conversionRate ?? 0.0);

            return $rightValue <=> $leftValue;
        });

        return $offers;
    }
}
