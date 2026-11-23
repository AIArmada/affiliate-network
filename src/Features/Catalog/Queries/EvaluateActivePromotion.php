<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Queries;

use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use Carbon\CarbonImmutable;
use Throwable;

final class EvaluateActivePromotion
{
    /**
     * @return array<string, mixed>|null
     */
    public function evaluate(OfferRecord $offer, CarbonImmutable $at): ?array
    {
        $promotions = $offer->activePromotions ?? [];
        $winner = null;
        $winningScore = null;

        foreach ($promotions as $promotion) {
            if (! is_array($promotion)) {
                continue;
            }

            if (! $this->withinWindow($promotion, $at)) {
                continue;
            }

            $score = $this->score($promotion);

            if ($winner === null || ($winningScore !== null && $score > $winningScore)) {
                $winner = $promotion;
                $winningScore = $score;
            }
        }

        return $winner;
    }

    /**
     * @param  array<string, mixed>  $promotion
     */
    private function withinWindow(array $promotion, CarbonImmutable $at): bool
    {
        try {
            $startsAt = $promotion['starts_at'] ?? null;

            if (is_string($startsAt) && $startsAt !== '' && $at->lt(CarbonImmutable::parse($startsAt))) {
                return false;
            }

            $endsAt = $promotion['ends_at'] ?? null;

            return ! (is_string($endsAt) && $endsAt !== '' && $at->gt(CarbonImmutable::parse($endsAt)));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $promotion
     */
    private function score(array $promotion): float
    {
        if (isset($promotion['override_rate_bp']) && is_numeric($promotion['override_rate_bp'])) {
            return (float) $promotion['override_rate_bp'];
        }

        if (isset($promotion['bonus_rate_bp']) && is_numeric($promotion['bonus_rate_bp'])) {
            return (float) $promotion['bonus_rate_bp'];
        }

        if (isset($promotion['bonus_fixed_minor']) && is_numeric($promotion['bonus_fixed_minor'])) {
            return (float) $promotion['bonus_fixed_minor'];
        }

        if (isset($promotion['multiplier']) && is_numeric($promotion['multiplier'])) {
            return (float) $promotion['multiplier'] * 10000.0;
        }

        return 0.0;
    }
}
