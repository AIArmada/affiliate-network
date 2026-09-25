<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;

/**
 * Finalizes the provisional network leg after the engine has spoken.
 *
 * Last of the ordered attribution listeners (provisional, engine,
 * finalizer). Reads the `attribution_winner` order marker: engine wins
 * supersede the leg, network wins confirm and fulfill it. When the
 * engine abstained — or is not installed — the network wins by default.
 * Re-runs are safe: only provisional legs finalize.
 */
final class FinalizeNetworkAttribution
{
    public function __construct(
        private readonly NetworkBooks $books,
        private readonly Fulfillment $fulfillment,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        if (! config('affiliate-network.checkout.enabled', false)) {
            return;
        }

        $order = $event->order;
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        $legId = $metadata['network_attribution']['leg_id'] ?? null;

        if (! is_string($legId) || $legId === '') {
            return;
        }

        $leg = NetworkConversionLeg::query()->whereKey($legId)->first();

        if (! $leg instanceof NetworkConversionLeg || $leg->status !== LegStatus::Provisional) {
            return;
        }

        $winner = $metadata['attribution_winner'] ?? null;

        if ($winner === 'engine') {
            $reason = is_string($metadata['attribution_decision_reason'] ?? null)
                ? (string) $metadata['attribution_decision_reason']
                : 'engine_won';

            $this->books->supersede($leg, $reason);

            return;
        }

        $this->writeWinner($order, $metadata);
        $confirmed = $this->books->confirm($leg);
        $this->fulfillment->fulfill($confirmed);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function writeWinner(Order $order, array $metadata): void
    {
        if (isset($metadata['attribution_winner'])) {
            return;
        }

        $metadata['attribution_winner'] = 'network';
        $metadata['attribution_decision_reason'] = array_key_exists('engine_touch_at', $metadata)
            ? 'engine_abstained'
            : 'network_alone';

        $order->update(['metadata' => $metadata]);
    }
}
