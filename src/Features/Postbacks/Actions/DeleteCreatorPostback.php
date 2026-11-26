<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;

final readonly class DeleteCreatorPostback
{
    public function execute(string $endpointId): void
    {
        (new CreatorPostbackEndpoint)->getConnection()->transaction(function () use ($endpointId): void {
            $endpoint = CreatorPostbackEndpoint::query()->whereKey($endpointId)->lockForUpdate()->first();

            if ($endpoint === null) {
                return;
            }

            CreatorPostbackDelivery::query()
                ->where('endpoint_id', $endpointId)
                ->delete();

            $endpoint->delete();
        });
    }
}
