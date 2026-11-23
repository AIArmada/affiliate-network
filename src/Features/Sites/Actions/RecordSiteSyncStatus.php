<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;

final class RecordSiteSyncStatus
{
    public function execute(string $siteId, SiteSyncStatus $status): void
    {
        AffiliateSite::query()
            ->withoutGlobalScopes()
            ->whereKey($siteId)
            ->update([
                'sync_status' => $status,
                'last_synced_at' => CarbonImmutable::now(),
            ]);
    }
}
