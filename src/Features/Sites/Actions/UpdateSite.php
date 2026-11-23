<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\Concerns\ValidatesSiteInput;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

            $originalDomain = mb_strtolower((string) $site->domain);
            $originalStatus = $site->status;

            $data += ['status' => $site->status];
            $validated = self::normalizeSiteData(Validator::make($data, self::siteRules($site))->validate());

            $status = self::resolveStatus($validated['status'] ?? null);
            $validated['status'] = $status;

            $domainChanged = array_key_exists('domain', $validated)
                && mb_strtolower((string) $validated['domain']) !== $originalDomain;

            if ($domainChanged && $originalStatus === SiteStatus::Verified) {
                $validated['status'] = SiteStatus::Pending;
                $validated['verified_at'] = null;
                $validated['verification_method'] = null;
                $validated['verification_token'] = 'affiliatenetwork-verify-' . Str::random(32);
            } else {
                $validated['verified_at'] = match ($status) {
                    SiteStatus::Verified => $validated['verified_at'] ?? $site->verified_at ?? CarbonImmutable::now(),
                    SiteStatus::Suspended => $site->verified_at,
                    default => null,
                };
            }

            $site->update($validated);

            return $site->refresh();
        });
    }
}
