<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ConfirmConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\FulfillConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordOrderAttribution;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\SupersedeConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class FinalizeNetworkAttribution
{
    public function __construct(
        private readonly SitesApi $sites,
        private readonly ConfirmConversionLeg $confirmConversionLeg,
        private readonly SupersedeConversionLeg $supersedeConversionLeg,
        private readonly FulfillConversionLeg $fulfillConversionLeg,
        private readonly Fulfillment $fulfillment,
        private readonly AffiliateIdentityResolver $identities,
        private readonly RecordOrderAttribution $orderAttribution,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        if (! config('affiliate-network.checkout.enabled', false)) {
            return;
        }

        $order = $event->order;
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $attribution = $metadata['network_attribution'] ?? null;

        if (! is_array($attribution)) {
            return;
        }

        $legId = $attribution['leg_id'] ?? null;

        if (! is_string($legId) || $legId === '') {
            return;
        }

        $leg = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereKey($legId)
            ->first();

        if (! $leg instanceof NetworkConversionLeg || $leg->status !== LegStatus::Provisional) {
            return;
        }

        $snapshot = $leg->toSnapshot();

        $winner = $this->winner($metadata);
        $reason = $this->reason($metadata, $winner);

        $owner = $this->creatorOwner($snapshot->affiliateId);

        OwnerContext::withOwner($owner, function () use ($legId, $winner, $reason): void {
            if ($winner === 'engine') {
                $this->supersedeConversionLeg->execute(
                    $this->legModel($legId),
                    $reason,
                    ['attribution_winner' => 'engine', 'attribution_decision_reason' => $reason],
                );

                return;
            }

            $confirmed = $this->confirmConversionLeg->execute(
                $this->legModel($legId),
                ['attribution_winner' => 'network', 'attribution_decision_reason' => $reason],
            );

            $site = $confirmed->site_id === null ? null : $this->sites->findByIdUnscoped((string) $confirmed->site_id);

            if ($confirmed->status === LegStatus::Posted
                && $site !== null
                && ! $site->requireSettledForPayout
                && $this->fulfillment->fulfillsAutomatically()) {
                $confirmed = $this->fulfillConversionLeg->execute((string) $confirmed->getKey());
            }

            event(new NetworkConversionRecorded(
                leg: $confirmed->toSnapshot(),
                counterRevenueMinor: (int) $confirmed->revenue_minor,
                currency: $confirmed->revenue_currency,
            ));
        });

        $this->orderAttribution->writeDecision($order, $metadata, $winner, $reason);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function winner(array $metadata): string
    {
        $winner = $metadata['attribution_winner'] ?? null;

        return $winner === 'engine' ? 'engine' : 'network';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function reason(array $metadata, string $winner): string
    {
        $reason = $metadata['attribution_decision_reason'] ?? null;

        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        if ($winner === 'engine') {
            return 'engine_won';
        }

        return array_key_exists('engine_touch_at', $metadata) ? 'engine_abstained' : 'network_alone';
    }

    private function legModel(string $legId): NetworkConversionLeg
    {
        return NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereKey($legId)
            ->firstOrFail();
    }

    private function creatorOwner(string $affiliateId): ?Model
    {
        $identity = $this->identities->find($affiliateId);

        if ($identity === null) {
            return null;
        }

        try {
            return OwnerContext::fromTypeAndId($identity->ownerType, $identity->ownerId);
        } catch (InvalidArgumentException | ModelNotFoundException) {
            return null;
        }
    }
}
