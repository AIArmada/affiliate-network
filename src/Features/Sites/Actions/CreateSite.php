<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\Concerns\ValidatesSiteInput;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class CreateSite
{
    use ValidatesSiteInput;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AffiliateSite
    {
        $data += ['status' => SiteStatus::Pending->value];
        $validated = self::normalizeSiteData(Validator::make($data, self::siteRules())->validate());

        $status = self::resolveStatus($validated['status'] ?? null);
        $validated['status'] = $status;
        $validated['verified_at'] = $status === SiteStatus::Verified
            ? ($validated['verified_at'] ?? CarbonImmutable::now())
            : null;

        return AffiliateSite::query()->create($validated);
    }
}
