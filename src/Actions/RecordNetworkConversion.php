<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\ConversionLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RecordNetworkConversion
{
    public function execute(AffiliateOfferLink $link, int $revenueMinor = 0, ?string $orderId = null, string $currency = 'USD'): void
    {
        $reference = $orderId !== null ? "{$orderId}::{$link->getKey()}" : uniqid('conv_', true);

        $exists = ConversionLedger::where('reference', $reference)->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use ($link, $revenueMinor, $orderId, $reference, $currency): void {
            ConversionLedger::create([
                'order_id' => $orderId,
                'link_id' => $link->getKey(),
                'offer_id' => $link->offer_id,
                'affiliate_id' => $link->affiliate_id,
                'site_id' => $link->site_id,
                'link_owner_type' => $link->getAttribute('owner_type') ?? null,
                'link_owner_id' => $link->getAttribute('owner_id') ?? null,
                'revenue' => $revenueMinor,
                'currency' => $currency,
                'reference' => $reference,
                'converted_at' => CarbonImmutable::now(),
            ]);

            $link->recordConversion($revenueMinor);

            event(new NetworkConversionRecorded($link, $revenueMinor));
        });
    }
}
