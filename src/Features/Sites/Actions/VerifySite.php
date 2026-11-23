<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VerifySite
{
    public function execute(AffiliateSite $site, ?string $method = null): AffiliateSite
    {
        return DB::transaction(function () use ($site, $method): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();

            if ($site->status === SiteStatus::Verified) {
                return $site;
            }

            if ($site->status !== SiteStatus::Pending) {
                throw new RuntimeException('Only pending sites can be verified.');
            }

            $attributes = [
                'status' => SiteStatus::Verified,
                'verified_at' => $site->verified_at ?? CarbonImmutable::now(),
            ];

            if ($method !== null && $method !== '') {
                $attributes['verification_method'] = $method;
            }

            $site->update($attributes);

            return $site->refresh();
        });
    }
}
