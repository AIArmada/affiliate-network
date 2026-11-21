<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Actions\Concerns\ValidatesSiteInput;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class UpdateSite
{
    use ValidatesSiteInput;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateSite $site, array $data): AffiliateSite
    {
        if (is_string($data['domain'] ?? null)) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        return DB::transaction(function () use ($site, $data): AffiliateSite {
            $site = AffiliateSite::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();
            $data += ['status' => $site->status];
            $validated = self::normalizeSiteData(Validator::make($data, self::siteRules($site), self::siteValidationMessages())->validate());

            $validated['verified_at'] = match ($validated['status']) {
                AffiliateSite::STATUS_VERIFIED => $validated['verified_at'] ?? $site->verified_at ?? CarbonImmutable::now(),
                AffiliateSite::STATUS_SUSPENDED => $site->verified_at,
                default => null,
            };

            $site->update($validated);

            return $site->refresh();
        });
    }
}
