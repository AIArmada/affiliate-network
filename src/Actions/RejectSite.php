<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RejectSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        return DB::transaction(function () use ($site): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === AffiliateSite::STATUS_REJECTED) {
                return $site;
            }

            if ($site->status !== AffiliateSite::STATUS_PENDING) {
                throw new RuntimeException('Only pending sites can be rejected.');
            }

            $site->update(['status' => AffiliateSite::STATUS_REJECTED]);

            return $site->refresh();
        });
    }
}
