<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReinstateSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        return DB::transaction(function () use ($site): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === SiteStatus::Verified) {
                return $site;
            }

            if ($site->status !== SiteStatus::Suspended) {
                throw new RuntimeException('Only suspended sites can be reinstated.');
            }

            $site->update([
                'status' => SiteStatus::Verified,
                'verified_at' => $site->verified_at ?? CarbonImmutable::now(),
            ]);

            return $site->refresh();
        });
    }
}
