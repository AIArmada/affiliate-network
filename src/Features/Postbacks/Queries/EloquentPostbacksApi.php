<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Queries;

use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Postbacks\Api\PostbacksApi;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;

final class EloquentPostbacksApi implements PostbacksApi
{
    public function endpointsFor(string $affiliateId, ?string $offerId, PostbackEventType $eventType): array
    {
        $records = [];

        $endpoints = CreatorPostbackEndpoint::query()
            ->where('affiliate_id', $affiliateId)
            ->where('is_active', true)
            ->where(function ($query) use ($offerId): void {
                $query->whereNull('offer_id');

                if ($offerId !== null) {
                    $query->orWhere('offer_id', $offerId);
                }
            })
            ->get();

        foreach ($endpoints as $endpoint) {
            if (! $endpoint->subscribesTo($eventType)) {
                continue;
            }

            $records[] = $endpoint->toRecord();
        }

        return $records;
    }

    public function deliveryStatus(string $deliveryId): ?array
    {
        $delivery = CreatorPostbackDelivery::query()->whereKey($deliveryId)->first();

        if (! $delivery instanceof CreatorPostbackDelivery) {
            return null;
        }

        return [
            'id' => (string) $delivery->getKey(),
            'status' => $delivery->status instanceof PostbackDeliveryStatus
                ? $delivery->status
                : PostbackDeliveryStatus::Pending,
            'http_status' => $delivery->http_status,
            'attempts' => (int) $delivery->attempts,
        ];
    }
}
