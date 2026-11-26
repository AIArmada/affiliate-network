<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Api;

use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;

interface PostbacksApi
{
    /** @param array<string, mixed> $data */
    public function configure(string $affiliateId, array $data): CreatorPostbackRecord;

    /** @param array<string, mixed> $data */
    public function update(string $endpointId, string $affiliateId, array $data): CreatorPostbackRecord;

    public function delete(string $endpointId): void;

    public function retry(string $deliveryId): PostbackDeliveryStatus;

    /**
     * @return list<CreatorPostbackRecord>
     */
    public function endpointsFor(string $affiliateId, ?string $offerId, PostbackEventType $eventType): array;

    /**
     * @return array{id: string, status: PostbackDeliveryStatus, http_status: int|null, attempts: int}|null
     */
    public function deliveryStatus(string $deliveryId): ?array;
}
