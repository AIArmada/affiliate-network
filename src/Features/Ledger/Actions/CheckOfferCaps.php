<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Exceptions\CapExceededException;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class CheckOfferCaps
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
    ) {}

    public function checkReason(
        string $offerId,
        ?string $affiliateId = null,
        int $proposedPayoutMinor = 0,
        ?CarbonImmutable $at = null,
    ): ?string {
        $offer = $this->catalog->findOfferUnscoped($offerId);

        if ($offer === null) {
            return null;
        }

        $at ??= CarbonImmutable::now();

        if ($offer->dailyConversionCap !== null
            && $this->counted($offerId, $at->startOfDay()) >= $offer->dailyConversionCap) {
            return 'daily_conversion_cap';
        }

        if ($offer->monthlyConversionCap !== null
            && $this->counted($offerId, $at->startOfMonth()) >= $offer->monthlyConversionCap) {
            return 'monthly_conversion_cap';
        }

        if ($offer->totalConversionCap !== null
            && $this->counted($offerId, null) >= $offer->totalConversionCap) {
            return 'total_conversion_cap';
        }

        if ($affiliateId !== null
            && $offer->perAffiliateConversionCap !== null
            && $this->counted($offerId, null, $affiliateId) >= $offer->perAffiliateConversionCap) {
            return 'per_affiliate_conversion_cap';
        }

        if ($offer->monthlyPayoutCapMinor !== null
            && $this->payoutTotal($offerId, $at->startOfMonth()) >= $offer->monthlyPayoutCapMinor) {
            return 'monthly_payout_cap_minor';
        }

        if ($offer->totalPayoutCapMinor !== null
            && $this->payoutTotal($offerId, null) >= $offer->totalPayoutCapMinor) {
            return 'total_payout_cap_minor';
        }

        $site = $this->sites->findByIdUnscoped($offer->siteId);

        if ($site !== null) {
            if ($site->monthlySpendCapMinor !== null
                && $this->siteSpend($offer->siteId, $at->startOfMonth()) >= $site->monthlySpendCapMinor) {
                return 'site_monthly_spend_cap_minor';
            }

            if ($site->prepaidBalanceMinor !== null
                && $proposedPayoutMinor > 0
                && $site->prepaidBalanceMinor < $proposedPayoutMinor) {
                return 'site_prepaid_balance';
            }
        }

        return null;
    }

    public function check(
        string $offerId,
        ?string $affiliateId = null,
        int $proposedPayoutMinor = 0,
        ?CarbonImmutable $at = null,
    ): void {
        $reason = $this->checkReason($offerId, $affiliateId, $proposedPayoutMinor, $at);

        if ($reason !== null) {
            throw CapExceededException::forOffer($offerId, $reason);
        }
    }

    private function counted(string $offerId, ?CarbonImmutable $since, ?string $affiliateId = null): int
    {
        $query = $this->countedQuery($offerId, $since);

        if ($affiliateId !== null) {
            $query->where('affiliate_id', $affiliateId);
        }

        return (int) $query->count();
    }

    private function payoutTotal(string $offerId, ?CarbonImmutable $since): int
    {
        return (int) $this->countedQuery($offerId, $since)->sum('payout_minor');
    }

    private function siteSpend(string $siteId, CarbonImmutable $since): int
    {
        return (int) NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->whereIn('status', [LegStatus::Pending, LegStatus::Posted, LegStatus::Reversed])
            ->whereNull('parent_leg_id')
            ->where('occurred_at', '>=', $since)
            ->sum('commission_minor');
    }

    /**
     * @return Builder<NetworkConversionLeg>
     */
    private function countedQuery(string $offerId, ?CarbonImmutable $since): Builder
    {
        $query = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->whereIn('status', [LegStatus::Pending, LegStatus::Posted, LegStatus::Reversed])
            ->whereNull('parent_leg_id');

        if ($since !== null) {
            $query->where('occurred_at', '>=', $since);
        }

        return $query;
    }
}
