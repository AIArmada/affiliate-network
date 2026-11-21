<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReinstateSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        return DB::transaction(function () use ($site): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === AffiliateSite::STATUS_VERIFIED) {
                return $site;
            }

            if ($site->status !== AffiliateSite::STATUS_SUSPENDED) {
                throw new RuntimeException('Only suspended sites can be reinstated.');
            }

            $site->update([
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verified_at' => $site->verified_at ?? CarbonImmutable::now(),
            ]);

            return $site->refresh();
        });
    }
}
