<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\Commissions\CommissionCaps;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\States\RejectedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Post one network conversion to the affiliates ledger.
 *
 * Network links only hold counters; the ledger is the system of record for
 * balances and payouts. Posting is idempotent on (network link, external
 * reference): redeliveries reuse the existing ledger row so money never
 * double-counts. Without an external reference there is nothing to dedupe
 * on, so the caller keeps counters only.
 */
final class PostNetworkConversionToLedger
{
    public function __construct(
        private readonly ApplyConversionAccounting $accounting,
        private readonly FraudDetectionService $fraud,
        private readonly Dispatcher $events,
    ) {}

    public function execute(
        AffiliateOfferLink $link,
        int $revenueMinor,
        ?string $currency,
        string $externalReference,
    ): ?AffiliateConversion {
        $affiliate = $link->relationLoaded('affiliate')
            ? $link->affiliate
            : $link->affiliate()->first();

        if (! $affiliate instanceof Affiliate) {
            return null;
        }

        $existing = AffiliateConversion::query()
            ->where('network_link_id', $link->getKey())
            ->where('external_reference', $externalReference)
            ->first();

        if ($existing instanceof AffiliateConversion) {
            return $existing;
        }

        $offer = $link->relationLoaded('offer') ? $link->offer : $link->offer()->first();

        if ($offer === null || ($offer->rate_fixed_minor === null && $offer->rate_base_bp === null)) {
            return null;
        }

        $resolvedCurrency = mb_strtoupper((string) ($currency
            ?? ($link->currency ?? null)
            ?? ($offer->currency ?? null)
            ?? config('affiliates.currency.default', 'MYR')));

        $commission = $offer->rate_fixed_minor !== null
            ? (int) $offer->rate_fixed_minor
            : (int) round(max(0, $revenueMinor) * (int) $offer->rate_base_bp / 10000);

        $conversion = new AffiliateConversion([
            'idempotency_key' => hash('sha256', implode('|', [
                'network',
                (string) $link->getKey(),
                $externalReference,
            ])),
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'network_link_id' => $link->getKey(),
            'subject_type' => 'network_order',
            'subject_key' => $externalReference,
            'external_reference' => $externalReference,
            'conversion_type' => 'network',
            'subtotal_minor' => max(0, $revenueMinor),
            'value_minor' => max(0, $revenueMinor),
            'commission_minor' => CommissionCaps::clamp($commission),
            'commission_currency' => $resolvedCurrency,
            'status' => config('affiliates.commissions.default_status', 'pending'),
            'origin' => 'network',
            'metadata' => [
                'offer_id' => $link->offer_id,
                'link_code' => $link->code,
                'site_id' => $link->site_id,
            ],
            'occurred_at' => CarbonImmutable::now(),
        ]);
        $conversion->forceFill([
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
        ]);
        $conversion->save();

        if (! $this->fraud->analyzeConversion($conversion)['allowed']) {
            $conversion->update(['status' => RejectedConversion::class]);
        }

        $this->accounting->handle($conversion);
        $this->events->dispatch(new AffiliateConversionRecorded(AffiliateConversionData::fromModel($conversion)));

        return $conversion;
    }
}
