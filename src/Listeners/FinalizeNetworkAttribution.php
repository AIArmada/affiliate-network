<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

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
        private readonly AffiliateIdentityResolver $identities,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        if (! config('affiliate-network.checkout.enabled', false)) {
            return;
        }

        $order = $event->order;
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        $attribution = $metadata['network_attribution'] ?? null;
        $legId = is_array($attribution) ? ($attribution['leg_id'] ?? null) : null;
        $linkId = is_array($attribution) ? ($attribution['link_id'] ?? null) : null;
        $affiliateId = is_array($attribution) ? ($attribution['affiliate_id'] ?? null) : null;
        $offerId = is_array($attribution) ? ($attribution['offer_id'] ?? null) : null;

        if (
            ! is_string($legId) || $legId === ''
            || ! is_string($linkId) || $linkId === ''
            || ! is_string($affiliateId) || $affiliateId === ''
            || ! is_string($offerId) || $offerId === ''
        ) {
            return;
        }

        $leg = OwnerContext::withOwner(null, fn (): ?NetworkConversionLeg => NetworkConversionLeg::query()
            ->withoutOwnerScope()
            ->whereKey($legId)
            ->where('link_id', $linkId)
            ->where('affiliate_id', $affiliateId)
            ->where('offer_id', $offerId)
            ->first());

        if (! $leg instanceof NetworkConversionLeg || $leg->status !== LegStatus::Provisional) {
            return;
        }

        $owner = $this->creatorOwner($leg);
        $decisionMetadata = $this->writeDecision($order, $metadata);
        $winner = $decisionMetadata['attribution_winner'];
        $reason = $decisionMetadata['attribution_decision_reason'];

        if ($winner === 'engine') {
            OwnerContext::withOwner($owner, function () use ($legId, $linkId, $affiliateId, $offerId, $reason, $decisionMetadata): void {
                $scopedLeg = NetworkConversionLeg::query()
                    ->whereKey($legId)
                    ->where('link_id', $linkId)
                    ->where('affiliate_id', $affiliateId)
                    ->where('offer_id', $offerId)
                    ->first();

                if ($scopedLeg instanceof NetworkConversionLeg && $scopedLeg->status === LegStatus::Provisional) {
                    $this->books->supersede($scopedLeg, $reason, $decisionMetadata);
                }
            });

            return;
        }

        OwnerContext::withOwner($owner, function () use ($legId, $linkId, $affiliateId, $offerId, $decisionMetadata): void {
            $scopedLeg = NetworkConversionLeg::query()
                ->whereKey($legId)
                ->where('link_id', $linkId)
                ->where('affiliate_id', $affiliateId)
                ->where('offer_id', $offerId)
                ->first();

            if (! $scopedLeg instanceof NetworkConversionLeg || $scopedLeg->status !== LegStatus::Provisional) {
                return;
            }

            $confirmed = $this->books->confirm($scopedLeg, $decisionMetadata);

            if ($this->fulfillment->fulfillsAutomatically()) {
                $this->fulfillment->fulfill($confirmed);
            }

            $link = $confirmed->link;

            if ($link instanceof AffiliateOfferLink) {
                $counterRevenue = $this->books->counterRevenue(
                    $link,
                    (int) $confirmed->revenue_minor,
                    $confirmed->revenue_currency,
                );

                event(new NetworkConversionRecorded(
                    $link,
                    $counterRevenue,
                    $confirmed->revenue_currency,
                    $confirmed,
                ));
            }
        });
    }

    private function creatorOwner(NetworkConversionLeg $leg): ?Model
    {
        $identity = $this->identities->find((string) $leg->affiliate_id);

        if ($identity === null) {
            return null;
        }

        try {
            return OwnerContext::fromTypeAndIdOrFail($identity->ownerType, $identity->ownerId);
        } catch (InvalidArgumentException | ModelNotFoundException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{attribution_winner: string, attribution_decision_reason: string}
     */
    private function writeDecision(Order $order, array $metadata): array
    {
        $winner = $metadata['attribution_winner'] ?? null;

        if ($winner !== 'engine') {
            $winner = 'network';
        }

        $reason = $metadata['attribution_decision_reason'] ?? null;

        if (! is_string($reason)) {
            $reason = $winner === 'engine'
                ? 'engine_won'
                : (array_key_exists('engine_touch_at', $metadata) ? 'engine_abstained' : 'network_alone');
        }

        if (($metadata['attribution_winner'] ?? null) !== $winner
            || ($metadata['attribution_decision_reason'] ?? null) !== $reason) {
            $metadata['attribution_winner'] = $winner;
            $metadata['attribution_decision_reason'] = $reason;
            $order->update(['metadata' => $metadata]);
        }

        return [
            'attribution_winner' => $winner,
            'attribution_decision_reason' => $reason,
        ];
    }
}
