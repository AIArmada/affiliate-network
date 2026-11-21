<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Actions\Concerns\ValidatesSiteInput;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
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
        if (is_string($data['domain'] ?? null)) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        $data += ['status' => AffiliateSite::STATUS_PENDING];
        $validated = self::normalizeSiteData(Validator::make($data, self::siteRules(), self::siteValidationMessages())->validate());

        $validated['verified_at'] = $validated['status'] === AffiliateSite::STATUS_VERIFIED
            ? ($validated['verified_at'] ?? CarbonImmutable::now())
            : null;

        return AffiliateSite::query()->create($validated);
    }
}
