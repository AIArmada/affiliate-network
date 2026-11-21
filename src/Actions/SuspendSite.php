<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SuspendSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        return DB::transaction(function () use ($site): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === AffiliateSite::STATUS_SUSPENDED) {
                return $site;
            }

            if ($site->status !== AffiliateSite::STATUS_VERIFIED) {
                throw new RuntimeException('Only verified sites can be suspended.');
            }

            $site->update(['status' => AffiliateSite::STATUS_SUSPENDED]);

            return $site->refresh();
        });
    }
}
