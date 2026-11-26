<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Exceptions\RestrictionViolatedException;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\DealRateOverride;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Platform\AffiliateProfileGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

final class PostConversionLeg
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly EnrollmentApi $enrollments,
        private readonly AffiliateProfileGuard $affiliateProfileGuard,
        private readonly CheckOfferCaps $caps,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $offerId,
        string $affiliateId,
        int $revenueMinor,
        ?string $currency,
        string $externalReference,
        string $eventType = 'purchase',
        LegStatus $status = LegStatus::Posted,
        ?string $linkId = null,
        ?string $couponId = null,
        ?string $couponCode = null,
        ?string $siteId = null,
        ?string $linkCode = null,
        ?string $subId = null,
        ?string $subId2 = null,
        ?string $subId3 = null,
        array $metadata = [],
        ?CarbonImmutable $occurredAt = null,
    ): NetworkConversionLeg {
        $idempotencyKey = $this->idempotencyKey($linkId, $couponId, $externalReference, $eventType);

        $existing = $this->findByIdempotencyKey($idempotencyKey);

        if ($existing instanceof NetworkConversionLeg) {
            return $existing;
        }

        $this->affiliateProfileGuard->assertCanWriteFor($affiliateId);

        $occurredAt ??= CarbonImmutable::now();
        $offer = $this->catalog->findOfferUnscoped($offerId);
        $site = $this->sites->findByIdUnscoped($siteId ?? $offer?->siteId ?? '');

        if ($offer instanceof OfferRecord) {
            $this->assertRestrictions($offer, $revenueMinor, $currency, $occurredAt);
        }

        $pricingCurrency = $this->pricingCurrency($offer, $site?->budgetCurrency);
        $resolvedCurrency = mb_strtoupper((string) ($currency ?? $pricingCurrency ?? config('affiliate-network.currency.default', 'MYR')));

        [$commission, $feeBp, $tierRateBp, $tierMinVolume] = $this->resolveCommission(
            $offer,
            $affiliateId,
            max(0, $revenueMinor),
            $resolvedCurrency,
            $pricingCurrency,
            $eventType,
            $occurredAt,
            $metadata,
        );

        $fee = min($commission, (int) round($commission * $feeBp / 10000));
        $payout = $commission - $fee;

        if ($status !== LegStatus::Provisional && $status !== LegStatus::Superseded && $commission > 0) {
            $this->caps->check((string) ($offer?->id ?? $offerId), $affiliateId, $payout, $occurredAt);
        }

        [$effectiveStatus, $locksAt, $lockedAt] = $this->lockingWindow($status, $offer, $site?->defaultLockingDays, $occurredAt);

        $attributes = [
            'link_id' => $linkId,
            'offer_id' => $offerId,
            'site_id' => $siteId ?? $offer?->siteId,
            'affiliate_id' => $affiliateId,
            'link_code' => $linkCode,
            'idempotency_key' => $idempotencyKey,
            'revenue_minor' => max(0, $revenueMinor),
            'revenue_currency' => $currency !== null ? mb_strtoupper($currency) : null,
            'commission_minor' => $commission,
            'commission_currency' => $resolvedCurrency,
            'fee_minor' => $fee,
            'fee_bp' => $feeBp,
            'payout_minor' => $payout,
            'tier_rate_bp' => $tierRateBp,
            'tier_min_volume_minor' => $tierMinVolume,
            'external_reference' => $externalReference,
            'event_type' => $eventType,
            'status' => $effectiveStatus,
            'coupon_code' => $couponCode,
            'sub_id' => $subId,
            'sub_id_2' => $subId2,
            'sub_id_3' => $subId3,
            'metadata' => $metadata === [] ? null : $metadata,
            'locks_at' => $locksAt,
            'locked_at' => $lockedAt,
            'occurred_at' => $occurredAt,
        ];

        try {
            $connection = (new NetworkConversionLeg)->getConnection();
            $leg = $connection->transaction(function () use ($attributes, $affiliateId, $connection): NetworkConversionLeg {
                $this->affiliateProfileGuard->lockForWrite($affiliateId, spl_object_id($connection));

                return NetworkConversionLeg::create($attributes);
            }, attempts: 1);
        } catch (QueryException $exception) {
            if (! self::isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findByIdempotencyKey($idempotencyKey);

            if (! $existing instanceof NetworkConversionLeg) {
                throw $exception;
            }

            return $existing;
        }

        if ($leg->wasRecentlyCreated
            && $site !== null
            && $site->prepaidBalanceMinor !== null
            && in_array($effectiveStatus, [LegStatus::Pending, LegStatus::Posted], true)
            && $commission > 0) {
            $this->sites->adjustPrepaidBalance((string) $site->id, -$commission);
        }

        return $leg;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?NetworkConversionLeg
    {
        return NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function idempotencyKey(?string $linkId, ?string $couponId, string $externalReference, string $eventType): string
    {
        $prefix = $linkId !== null && $linkId !== ''
            ? 'leg:link:' . $linkId
            : 'leg:coupon:' . (string) $couponId;

        return $prefix . ':' . $externalReference . ':' . $eventType;
    }

    private function assertRestrictions(
        OfferRecord $offer,
        int $revenueMinor,
        ?string $currency,
        CarbonImmutable $occurredAt,
    ): void {
        $reason = $this->catalog->evaluateRestrictions($offer, new RestrictionContext(
            orderMinor: max(0, $revenueMinor),
            currency: $currency,
        ));

        if ($reason !== null) {
            throw RestrictionViolatedException::forOffer($offer->id, $reason);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{int, int, int|null, int|null} commission, fee bp, tier rate, tier floor
     */
    private function resolveCommission(
        ?OfferRecord $offer,
        string $affiliateId,
        int $revenueMinor,
        string $currency,
        ?string $pricingCurrency,
        string $eventType,
        CarbonImmutable $occurredAt,
        array &$metadata,
    ): array {
        $defaultFeeBp = max(0, (int) config('affiliate-network.fees.default_bp', 0));

        if ($offer === null) {
            return [0, $defaultFeeBp, null, null];
        }

        if ($pricingCurrency !== null && mb_strtoupper($pricingCurrency) !== mb_strtoupper($currency)) {
            return [0, max(0, (int) ($offer->networkFeeBp ?? $defaultFeeBp)), null, null];
        }

        $offerFeeBp = $offer->networkFeeBp !== null ? max(0, (int) $offer->networkFeeBp) : $defaultFeeBp;

        $deal = $this->enrollments->activeDealFor($offer->id, $affiliateId, $occurredAt);

        if ($deal instanceof DealRateOverride) {
            $feeBp = $deal->networkFeeBp !== null ? max(0, (int) $deal->networkFeeBp) : $offerFeeBp;

            if ($deal->rateFixedMinor !== null) {
                return $this->applyPromotion(max(0, (int) $deal->rateFixedMinor), $revenueMinor, $offer, $occurredAt, $feeBp, null, null, $metadata);
            }

            $tier = $this->matchingTier($deal->volumeTiers, $offer->id, $affiliateId, $revenueMinor, $currency);

            if ($tier !== null) {
                return $this->applyPromotion(
                    (int) round($revenueMinor * $tier['rate_bp'] / 10000),
                    $revenueMinor,
                    $offer,
                    $occurredAt,
                    $feeBp,
                    $tier['rate_bp'],
                    $tier['min_volume_minor'],
                    $metadata,
                );
            }

            if ($deal->rateBaseBp !== null) {
                return $this->applyPromotion(
                    (int) round($revenueMinor * (int) $deal->rateBaseBp / 10000),
                    $revenueMinor,
                    $offer,
                    $occurredAt,
                    $feeBp,
                    null,
                    null,
                    $metadata,
                );
            }
        }

        $eventRate = is_array($offer->eventRates) ? ($offer->eventRates[$eventType] ?? null) : null;

        if (is_array($eventRate)) {
            $fixed = isset($eventRate['rate_fixed_minor']) && is_numeric($eventRate['rate_fixed_minor'])
                ? (int) $eventRate['rate_fixed_minor']
                : null;
            $baseBp = isset($eventRate['rate_base_bp']) && is_numeric($eventRate['rate_base_bp'])
                ? (int) $eventRate['rate_base_bp']
                : null;

            if ($fixed !== null) {
                return [max(0, $fixed), $offerFeeBp, null, null];
            }

            if ($baseBp !== null) {
                return [(int) round($revenueMinor * $baseBp / 10000), $offerFeeBp, null, null];
            }
        }

        if ($offer->rateFixedMinor !== null) {
            $commission = max(0, (int) $offer->rateFixedMinor);
        } else {
            $tier = $this->matchingTier($offer->volumeTiers, $offer->id, $affiliateId, $revenueMinor, $currency);

            if ($tier !== null) {
                return $this->applyPromotion(
                    (int) round($revenueMinor * $tier['rate_bp'] / 10000),
                    $revenueMinor,
                    $offer,
                    $occurredAt,
                    $offerFeeBp,
                    $tier['rate_bp'],
                    $tier['min_volume_minor'],
                    $metadata,
                );
            }

            if ($offer->rateBaseBp === null) {
                return [0, $offerFeeBp, null, null];
            }

            $commission = (int) round($revenueMinor * (int) $offer->rateBaseBp / 10000);
        }

        return $this->applyPromotion($commission, $revenueMinor, $offer, $occurredAt, $offerFeeBp, null, null, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{int, int, int|null, int|null}
     */
    private function applyPromotion(
        int $commission,
        int $revenueMinor,
        OfferRecord $offer,
        CarbonImmutable $occurredAt,
        int $feeBp,
        ?int $tierRateBp,
        ?int $tierMinVolume,
        array &$metadata,
    ): array {
        $promotion = $this->catalog->evaluateActivePromotion($offer, $occurredAt);

        if ($promotion === null) {
            return [$commission, $feeBp, $tierRateBp, $tierMinVolume];
        }

        if (isset($promotion['override_rate_bp']) && is_numeric($promotion['override_rate_bp'])) {
            $commission = (int) round($revenueMinor * ((int) $promotion['override_rate_bp'] / 10000));
        } elseif (isset($promotion['bonus_rate_bp']) && is_numeric($promotion['bonus_rate_bp'])) {
            $commission += (int) round($revenueMinor * ((int) $promotion['bonus_rate_bp'] / 10000));
        } elseif (isset($promotion['bonus_fixed_minor']) && is_numeric($promotion['bonus_fixed_minor'])) {
            $commission += max(0, (int) $promotion['bonus_fixed_minor']);
        } elseif (isset($promotion['multiplier']) && is_numeric($promotion['multiplier'])) {
            $commission = (int) round($commission * (float) $promotion['multiplier']);
        }

        $promotionId = $promotion['id'] ?? $promotion['promotion_id'] ?? null;

        if (is_string($promotionId) && $promotionId !== '') {
            $metadata['applied_promotion_id'] = $promotionId;
        }

        return [max(0, $commission), $feeBp, $tierRateBp, $tierMinVolume];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $tiers
     * @return array{rate_bp: int, min_volume_minor: int}|null
     */
    private function matchingTier(
        ?array $tiers,
        string $offerId,
        string $affiliateId,
        int $revenueMinor,
        string $currency,
    ): ?array {
        if (! is_array($tiers) || $tiers === []) {
            return null;
        }

        $cumulative = (int) NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->where('affiliate_id', $affiliateId)
            ->whereIn('status', [LegStatus::Posted, LegStatus::Reversed])
            ->where('commission_currency', $currency)
            ->sum('revenue_minor') + $revenueMinor;

        $match = null;

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $tierCurrency = isset($tier['currency']) ? mb_strtoupper((string) $tier['currency']) : $currency;

            if ($tierCurrency !== $currency) {
                continue;
            }

            $floor = (int) ($tier['min_volume_minor'] ?? 0);

            if ($cumulative < $floor) {
                continue;
            }

            if ($match === null || $floor > $match['min_volume_minor']) {
                $match = ['rate_bp' => (int) ($tier['rate_bp'] ?? 0), 'min_volume_minor' => $floor];
            }
        }

        return $match;
    }

    /**
     * @return array{LegStatus, CarbonImmutable|null, CarbonImmutable|null}
     */
    private function lockingWindow(
        LegStatus $status,
        ?OfferRecord $offer,
        ?int $siteDefaultDays,
        CarbonImmutable $occurredAt,
    ): array {
        if ($status !== LegStatus::Posted) {
            return [$status, null, null];
        }

        $lockingDays = $this->effectiveLockingDays($offer, $siteDefaultDays);

        if ($lockingDays > 0) {
            return [LegStatus::Pending, $occurredAt->addDays($lockingDays), null];
        }

        return [LegStatus::Posted, null, $occurredAt];
    }

    private function effectiveLockingDays(?OfferRecord $offer, ?int $siteDefaultDays): int
    {
        if ($offer !== null && $offer->lockingDays > 0) {
            return $offer->lockingDays;
        }

        if ($siteDefaultDays !== null && $siteDefaultDays > 0) {
            return $siteDefaultDays;
        }

        return max(0, (int) config('affiliate-network.locking.default_days', 0));
    }

    private function pricingCurrency(?OfferRecord $offer, ?string $budgetCurrency): ?string
    {
        if ($offer !== null && $offer->currency !== null && $offer->currency !== '') {
            return $offer->currency;
        }

        return $budgetCurrency;
    }

    private static function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
