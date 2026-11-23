<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Queries;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Support\Str;

final class SiteLookupQuery
{
    public function findById(string $siteId): ?AffiliateSite
    {
        return AffiliateSite::query()->whereKey($siteId)->first();
    }

    public function findByIdUnscoped(string $siteId): ?AffiliateSite
    {
        return AffiliateSite::query()->withoutGlobalScopes()->whereKey($siteId)->first();
    }

    public function findByIdOrDomainUnscoped(string $identifier): ?AffiliateSite
    {
        $identifier = mb_trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $query = AffiliateSite::query()->withoutGlobalScopes();

        if (Str::isUuid($identifier)) {
            return $query->whereKey($identifier)->first();
        }

        return $query->where('domain', mb_strtolower($identifier))->first();
    }
}
