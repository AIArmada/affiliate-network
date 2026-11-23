<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RejectSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        return DB::transaction(function () use ($site): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === SiteStatus::Rejected) {
                return $site;
            }

            if ($site->status !== SiteStatus::Pending) {
                throw new RuntimeException('Only pending sites can be rejected.');
            }

            $site->update([
                'status' => SiteStatus::Rejected,
                'verified_at' => null,
            ]);

            return $site->refresh();
        });
    }
}
