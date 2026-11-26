<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Postbacks\EntryPoints\Jobs\DeliverCreatorPostbackJob;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;

final readonly class EnqueueCreatorPostbacks
{
    public function execute(NetworkLegSnapshot $leg, PostbackEventType $eventType): int
    {
        if (! config('affiliate-network.creator_postbacks.enabled', true)) {
            return 0;
        }

        $endpoints = CreatorPostbackEndpoint::query()
            ->where('affiliate_id', $leg->affiliateId)
            ->where('is_active', true)
            ->where(function ($query) use ($leg): void {
                $query->whereNull('offer_id')->orWhere('offer_id', $leg->offerId);
            })
            ->get();

        $queue = config('affiliate-network.creator_postbacks.queue');
        $queue = is_string($queue) && $queue !== '' ? $queue : null;

        $enqueued = 0;

        foreach ($endpoints as $endpoint) {
            if (! $endpoint->subscribesTo($eventType)) {
                continue;
            }

            $delivery = CreatorPostbackDelivery::query()->firstOrCreate(
                [
                    'endpoint_id' => $endpoint->getKey(),
                    'leg_id' => $leg->id,
                    'event_type' => $eventType->value,
                ],
                [
                    'resolved_url' => $this->resolveUrl($endpoint, $leg, $eventType),
                    'status' => PostbackDeliveryStatus::Pending,
                    'attempts' => 0,
                ],
            );

            if (! $delivery->wasRecentlyCreated) {
                continue;
            }

            $job = DeliverCreatorPostbackJob::dispatch((string) $delivery->getKey())->afterCommit();

            if ($queue !== null) {
                $job->onQueue($queue);
            }

            $enqueued++;
        }

        return $enqueued;
    }

    private function resolveUrl(CreatorPostbackEndpoint $endpoint, NetworkLegSnapshot $leg, PostbackEventType $eventType): string
    {
        $replacements = [
            '{leg_id}' => $leg->id,
            '{offer_id}' => $leg->offerId,
            '{link_code}' => (string) ($leg->linkCode ?? ''),
            '{external_reference}' => $leg->externalReference,
            '{event_type}' => $eventType->value,
            '{status}' => $leg->status->value,
            '{revenue_minor}' => (string) $leg->revenueMinor,
            '{commission_minor}' => (string) $leg->commissionMinor,
            '{payout_minor}' => (string) $leg->payoutMinor,
            '{currency}' => $leg->commissionCurrency,
            '{sub1}' => (string) ($leg->subId ?? ''),
            '{sub2}' => (string) ($leg->subId2 ?? ''),
            '{sub3}' => (string) ($leg->subId3 ?? ''),
            '{coupon_code}' => (string) ($leg->couponCode ?? ''),
        ];

        $encoded = [];

        foreach ($replacements as $token => $value) {
            $encoded[$token] = rawurlencode($value);
        }

        return strtr((string) $endpoint->url_template, $encoded);
    }
}
