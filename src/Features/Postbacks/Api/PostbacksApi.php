<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Api;

use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;

interface PostbacksApi
{
    /**
     * @return list<CreatorPostbackRecord>
     */
    public function endpointsFor(string $affiliateId, ?string $offerId, PostbackEventType $eventType): array;

    /**
     * @return array{id: string, status: PostbackDeliveryStatus, http_status: int|null, attempts: int}|null
     */
    public function deliveryStatus(string $deliveryId): ?array;
}
