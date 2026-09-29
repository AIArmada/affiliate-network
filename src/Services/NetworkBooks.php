<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Network books: the marketplace system of record for money.
 *
 * Every conversion posts one append-only leg carrying the full split:
 * revenue, commission (fixed, tier, or base rate), network fee, and
 * creator payout. Legs are never edited except for status finalization
 * (provisional → posted/superseded) and reversal markers. Balances,
 * counters, and reconciliation derive from these rows.
 */
final class NetworkBooks
{
    public function post(
        AffiliateOfferLink $link,
        int $revenueMinor,
        ?string $currency,
        string $externalReference,
        LegStatus $status = LegStatus::Posted,
    ): NetworkConversionLeg {
        $existing = NetworkConversionLeg::query()
            ->where('link_id', $link->getKey())
            ->where('external_reference', $externalReference)
            ->first();

        if ($existing instanceof NetworkConversionLeg) {
            return $existing;
        }

        $offer = $link->relationLoaded('offer') ? $link->offer : $link->offer()->first();

        $resolvedCurrency = mb_strtoupper((string) ($currency
            ?? ($link->currency ?? null)
            ?? ($offer->currency ?? null)
            ?? config('affiliate-network.currency.default', 'MYR')));

        [$commission, $tierRateBp, $tierMinVolume] = $this->commissionFor($link, $offer, max(0, $revenueMinor), $resolvedCurrency);

        $feeBp = $offer->network_fee_bp ?? config('affiliate-network.fees.default_bp', 0);
        $feeBp = max(0, (int) $feeBp);
        $fee = min($commission, (int) round($commission * $feeBp / 10000));

        return NetworkConversionLeg::create([
            'link_id' => $link->getKey(),
            'offer_id' => $link->offer_id,
            'site_id' => $link->site_id,
            'affiliate_id' => (string) $link->affiliate_id,
            'link_code' => (string) $link->trackedSlug(),
            'revenue_minor' => max(0, $revenueMinor),
            'revenue_currency' => $currency !== null ? mb_strtoupper($currency) : null,
            'commission_minor' => $commission,
            'commission_currency' => $resolvedCurrency,
            'fee_minor' => $fee,
            'fee_bp' => $feeBp,
            'payout_minor' => $commission - $fee,
            'tier_rate_bp' => $tierRateBp,
            'tier_min_volume_minor' => $tierMinVolume,
            'external_reference' => $externalReference,
            'status' => $status,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    public function confirm(NetworkConversionLeg $leg): NetworkConversionLeg
    {
        if ($leg->status !== LegStatus::Provisional) {
            return $leg;
        }

        $leg->update(['status' => LegStatus::Posted]);

        return $leg->refresh();
    }

    public function supersede(NetworkConversionLeg $leg, string $reason): NetworkConversionLeg
    {
        if ($leg->status !== LegStatus::Provisional) {
            return $leg;
        }

        $leg->update([
            'status' => LegStatus::Superseded,
            'metadata' => array_merge($leg->metadata ?? [], ['superseded_reason' => $reason]),
        ]);

        return $leg->refresh();
    }

    /**
     * Rebuild a link's cached counters from its legs.
     *
     * Counters are write-time caches; this is the repair path and the
     * proof that legs are the source of truth. Reversal-companion rows
     * never counted and are excluded.
     *
     * @return array{conversions: int, revenue_minor: int}
     */
    public function recountLinkCounters(AffiliateOfferLink $link): array
    {
        $legs = NetworkConversionLeg::query()->where('link_id', $link->getKey())->get();

        $conversions = 0;
        $revenue = 0;
        $linkCurrency = $link->currency !== null ? mb_strtoupper($link->currency) : null;

        foreach ($legs as $leg) {
            if (is_array($leg->metadata) && isset($leg->metadata['reverses_id'])) {
                continue;
            }

            $conversions++;

            $legCurrency = $leg->revenue_currency !== null ? mb_strtoupper($leg->revenue_currency) : null;

            if ($linkCurrency === null || $legCurrency === null || $legCurrency === $linkCurrency) {
                $revenue += (int) $leg->revenue_minor;
            }
        }

        $link->forceFill(['conversions' => $conversions, 'revenue' => $revenue])->save();

        return ['conversions' => $conversions, 'revenue_minor' => $revenue];
    }

    public function reverse(NetworkConversionLeg $leg, string $reason): NetworkConversionLeg
    {
        return DB::transaction(function () use ($leg, $reason): NetworkConversionLeg {
            $locked = NetworkConversionLeg::query()
                ->whereKey($leg->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (is_array($locked->metadata) && isset($locked->metadata['reverses_id'])) {
                // Companions are reversal records, not reversible
                // originals: re-reversing one is an idempotent no-op
                // (retry-safe), never a second-order leg.
                return $locked;
            }

            if ($locked->status === LegStatus::Reversed) {
                $existing = $this->findCompanionLeg($locked);

                if ($existing !== null) {
                    // The merchant void is idempotent: re-run it so a
                    // retry also settles postings a previous
                    // implementation left payable behind its companion.
                    $this->voidPostedConversion($locked, $reason);

                    return $existing;
                }

                // A companionless reversed leg is legacy damage (a
                // previous non-transactional implementation could leave
                // it behind): repair it idempotently instead of silently
                // returning the leg with no reversal leg and no void.
                return $this->createCompanionLeg($locked, $reason);
            }

            $locked->update(['status' => LegStatus::Reversed]);

            return $this->createCompanionLeg($locked, $reason);
        }, attempts: 3);
    }

    private function createCompanionLeg(NetworkConversionLeg $locked, string $reason): NetworkConversionLeg
    {
        $companion = NetworkConversionLeg::create([
            'link_id' => $locked->link_id,
            'offer_id' => $locked->offer_id,
            'site_id' => $locked->site_id,
            'affiliate_id' => $locked->affiliate_id,
            'link_code' => $locked->link_code,
            'revenue_minor' => 0,
            'revenue_currency' => $locked->revenue_currency,
            'commission_minor' => -1 * (int) $locked->commission_minor,
            'commission_currency' => $locked->commission_currency,
            'fee_minor' => -1 * (int) $locked->fee_minor,
            'fee_bp' => $locked->fee_bp,
            'payout_minor' => -1 * (int) $locked->payout_minor,
            'tier_rate_bp' => $locked->tier_rate_bp,
            'tier_min_volume_minor' => $locked->tier_min_volume_minor,
            // Keyed by original leg only: the reason lives in
            // metadata, so concurrent reversals with different
            // reasons converge on one companion (backstopped by the
            // unique link/external_reference pair).
            'external_reference' => self::reversalReference(
                (string) $locked->external_reference,
                (string) $locked->getKey()
            ),
            'status' => LegStatus::Reversed,
            'metadata' => ['reverses_id' => (string) $locked->getKey(), 'reversal_reason' => $reason],
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $this->voidPostedConversion($locked, $reason);

        return $companion;
    }

    private static function reversalReference(string $original, string $legId): string
    {
        // The column caps at 120 chars. The full leg id rides the suffix
        // instead of a truncated hash: same-link collisions are
        // impossible (leg ids are unique), the key stays deterministic
        // per leg, and the original prefix flexes to fit the column.
        $suffix = ':rev:' . $legId;

        return mb_substr($original, 0, max(0, 120 - mb_strlen($suffix))) . $suffix;
    }

    private function findCompanionLeg(NetworkConversionLeg $leg): ?NetworkConversionLeg
    {
        return NetworkConversionLeg::query()
            ->where('metadata->reverses_id', (string) $leg->getKey())
            ->first();
    }

    private function voidPostedConversion(NetworkConversionLeg $leg, string $reason): void
    {
        if (! app()->bound(NetworkLedger::class)) {
            return;
        }

        app(NetworkLedger::class)->voidPosting(
            (string) $leg->link_id,
            (string) $leg->external_reference,
            $reason,
        );
    }

    /**
     * Commission for one conversion: fixed rate wins, else the volume
     * tier matching cumulative affiliate+offer revenue (including this
     * conversion), else the base rate.
     *
     * @return array{int, int|null, int|null} commission, tier rate, tier floor
     */
    private function commissionFor(
        AffiliateOfferLink $link,
        ?AffiliateOffer $offer,
        int $revenueMinor,
        string $currency,
    ): array {
        if (! $offer instanceof AffiliateOffer) {
            return [0, null, null];
        }

        if ($offer->rate_fixed_minor !== null) {
            return [(int) $offer->rate_fixed_minor, null, null];
        }

        $tier = $this->matchingTier($link, $offer, $revenueMinor, $currency);

        $rateBp = $tier['rate_bp'] ?? $offer->rate_base_bp;

        if ($rateBp === null) {
            return [0, null, null];
        }

        return [
            (int) round($revenueMinor * (int) $rateBp / 10000),
            isset($tier['rate_bp']) ? (int) $tier['rate_bp'] : null,
            isset($tier['min_volume_minor']) ? (int) $tier['min_volume_minor'] : null,
        ];
    }

    /**
     * @return array{rate_bp: int, min_volume_minor: int}|null
     */
    private function matchingTier(
        AffiliateOfferLink $link,
        AffiliateOffer $offer,
        int $revenueMinor,
        string $currency,
    ): ?array {
        $tiers = $offer->volume_tiers;

        if (! is_array($tiers) || $tiers === []) {
            return null;
        }

        $cumulative = NetworkConversionLeg::query()
            ->where('offer_id', $offer->getKey())
            ->where('affiliate_id', (string) $link->affiliate_id)
            ->where('status', LegStatus::Posted)
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

            if ($match === null || $floor > (int) $match['min_volume_minor']) {
                $match = ['rate_bp' => (int) ($tier['rate_bp'] ?? 0), 'min_volume_minor' => $floor];
            }
        }

        return $match;
    }
}
