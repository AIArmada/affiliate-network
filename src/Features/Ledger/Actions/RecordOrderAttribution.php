<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;

final class RecordOrderAttribution
{
    public function writeTouch(Order $order, ?string $clickedAt): void
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $metadata['network_touch_at'] = $clickedAt;

        $order->update(['metadata' => $metadata]);
    }

    /**
     * @param  array{
     *     link_code: string|null,
     *     link_id: string|null,
     *     leg_id: string|null,
     *     affiliate_id: string,
     *     offer_id: string,
     *     site_id: string|null,
     *     clicked_at: string|null,
     *     sub1: string|null,
     *     sub2: string|null,
     *     sub3: string|null
     * }  $attribution
     */
    public function writeAttribution(Order $order, array $attribution): void
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        $metadata['network_attribution'] = array_merge($attribution, [
            'converted_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        $order->update(['metadata' => $metadata]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function writeDecision(Order $order, array $metadata, string $winner, string $reason): void
    {
        $current = is_array($order->metadata) ? $order->metadata : $metadata;

        $current['attribution_winner'] = $winner;
        $current['attribution_decision_reason'] = $reason;

        $order->update(['metadata' => $current]);
    }
}
