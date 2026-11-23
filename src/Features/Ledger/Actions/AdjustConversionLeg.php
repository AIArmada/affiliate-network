<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Events\ConversionAdjusted;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\ResolveLegOwnerQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdjustConversionLeg
{
    public function __construct(
        private readonly SitesApi $sites,
        private readonly AttributionApi $attribution,
        private readonly ResolveLegOwnerQuery $owners,
    ) {}

    public function execute(
        string $legId,
        string $adjustmentReference,
        int $deltaRevenueMinor,
        string $reason,
    ): NetworkConversionLeg {
        if (mb_trim($adjustmentReference) === '') {
            throw new InvalidArgumentException('Adjustments require a non-empty adjustment reference.');
        }

        if ($deltaRevenueMinor === 0) {
            throw new InvalidArgumentException('Adjustments require a non-zero revenue delta.');
        }

        $preview = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereKey($legId)
            ->firstOrFail();

        $owner = $this->owners->forLeg($preview);

        return OwnerContext::withOwner($owner, fn (): NetworkConversionLeg => $this->adjust(
            $legId,
            $adjustmentReference,
            $deltaRevenueMinor,
            $reason,
        ));
    }

    private function adjust(
        string $legId,
        string $adjustmentReference,
        int $deltaRevenueMinor,
        string $reason,
    ): NetworkConversionLeg {
        [$adjustment, $original] = DB::transaction(function () use ($legId, $adjustmentReference, $deltaRevenueMinor, $reason): array {
            $original = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->whereKey($legId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->parent_leg_id !== null) {
                throw new InvalidArgumentException('Companion legs cannot be adjusted.');
            }

            if (! in_array($original->status, [LegStatus::Pending, LegStatus::Posted], true)) {
                throw new InvalidArgumentException('Only pending or posted conversions can be adjusted.');
            }

            $idempotencyKey = 'adj:' . $original->getKey() . ':' . $adjustmentReference;

            $existing = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof NetworkConversionLeg) {
                return [$existing, $original];
            }

            $ratio = (int) $original->revenue_minor === 0
                ? 0.0
                : $deltaRevenueMinor / (int) $original->revenue_minor;

            $deltaCommission = (int) round((int) $original->commission_minor * $ratio);
            $deltaFee = (int) round((int) $original->fee_minor * $ratio);
            $deltaPayout = $deltaCommission - $deltaFee;

            $adjustment = NetworkConversionLeg::create([
                'link_id' => $original->link_id,
                'offer_id' => $original->offer_id,
                'site_id' => $original->site_id,
                'affiliate_id' => $original->affiliate_id,
                'link_code' => $original->link_code,
                'idempotency_key' => $idempotencyKey,
                'parent_leg_id' => $original->getKey(),
                'revenue_minor' => $deltaRevenueMinor,
                'revenue_currency' => $original->revenue_currency,
                'commission_minor' => $deltaCommission,
                'commission_currency' => $original->commission_currency,
                'fee_minor' => $deltaFee,
                'fee_bp' => $original->fee_bp,
                'payout_minor' => $deltaPayout,
                'tier_rate_bp' => $original->tier_rate_bp,
                'tier_min_volume_minor' => $original->tier_min_volume_minor,
                'external_reference' => $original->external_reference,
                'event_type' => $original->event_type,
                'status' => $original->status,
                'coupon_code' => $original->coupon_code,
                'sub_id' => $original->sub_id,
                'sub_id_2' => $original->sub_id_2,
                'sub_id_3' => $original->sub_id_3,
                'locks_at' => $original->locks_at,
                'locked_at' => $original->locked_at,
                'metadata' => array_merge($original->metadata ?? [], [
                    'adjustment_reference' => $adjustmentReference,
                    'adjustment_reason' => $reason,
                ]),
                'occurred_at' => CarbonImmutable::now(),
            ]);

            if ($original->link_id !== null) {
                $this->attribution->recordLinkCounters((string) $original->link_id, $deltaRevenueMinor, 0);
            }

            $site = $original->site_id === null ? null : $this->sites->findByIdUnscoped((string) $original->site_id);

            if ($site !== null && $site->prepaidBalanceMinor !== null && $deltaCommission !== 0) {
                $this->sites->adjustPrepaidBalance((string) $site->id, -$deltaCommission);
            }

            return [$adjustment, $original];
        });

        if (! $adjustment->wasRecentlyCreated) {
            return $adjustment;
        }

        event(new ConversionAdjusted($original->toSnapshot(), $adjustment->toSnapshot(), $reason));

        return $adjustment;
    }
}
