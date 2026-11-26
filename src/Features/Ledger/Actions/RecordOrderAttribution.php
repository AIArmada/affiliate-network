<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Actions\MergeOrderMetadata;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;

final readonly class RecordOrderAttribution
{
    public function __construct(private MergeOrderMetadata $orderMetadata) {}

    public function writeTouch(Order $order, ?string $clickedAt): void
    {
        $this->merge($order, ['network_touch_at' => $clickedAt]);
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
        $this->merge($order, ['network_attribution' => array_merge($attribution, [
            'converted_at' => CarbonImmutable::now()->toIso8601String(),
        ])]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function writeDecision(Order $order, array $metadata, string $winner, string $reason): void
    {
        $this->merge($order, ['attribution_winner' => $winner, 'attribution_decision_reason' => $reason]);
    }

    /** @param array<string, mixed> $patch */
    private function merge(Order $order, array $patch): void
    {
        $owner = OwnerTupleParser::fromTypeAndId($order->owner_type, $order->owner_id)->toOwnerModel();

        OwnerContext::withOwner($owner, function () use ($order, $patch): void {
            $this->orderMetadata->handle((string) $order->getKey(), $patch);
            $order->refresh();
        });
    }
}
