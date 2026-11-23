<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SuspendSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        return DB::transaction(function () use ($site): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === SiteStatus::Suspended) {
                return $site;
            }

            if ($site->status !== SiteStatus::Verified) {
                throw new RuntimeException('Only verified sites can be suspended.');
            }

            $site->update(['status' => SiteStatus::Suspended]);

            return $site->refresh();
        });
    }
}
