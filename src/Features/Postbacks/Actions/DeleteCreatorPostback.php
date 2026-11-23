<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;

final class DeleteCreatorPostback
{
    public function execute(string $endpointId): void
    {
        CreatorPostbackDelivery::query()
            ->where('endpoint_id', $endpointId)
            ->delete();

        CreatorPostbackEndpoint::query()
            ->whereKey($endpointId)
            ->delete();
    }
}
