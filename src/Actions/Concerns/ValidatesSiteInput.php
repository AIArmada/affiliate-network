<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions\Concerns;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

trait ValidatesSiteInput
{
    /**
     * @return array<string, mixed>
     */
    private static function siteRules(?AffiliateSite $site = null): array
    {
        $domainIsUnique = Rule::unique(AffiliateSite::class, 'domain');

        if ($site !== null) {
            $domainIsUnique->ignore($site);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/', $domainIsUnique],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(AffiliateSite::STATUSES)],
            'verification_method' => ['nullable', 'string', 'in:dns,meta_tag,file'],
            'verified_at' => ['nullable', 'date'],
            'catalog_url' => ['nullable', 'url:http,https', 'max:255'],
            'catalog_token' => ['nullable', 'string', 'max:512'],
            'settings' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function siteValidationMessages(): array
    {
        return ['domain.regex' => 'The domain must be a valid hostname.'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeSiteData(array $data): array
    {
        $token = $data['catalog_token'] ?? null;
        unset($data['catalog_token']);

        if (is_string($token) && $token !== '') {
            $data['catalog_token_encrypted'] = encrypt($token);
            $data['catalog_token_issued_at'] = CarbonImmutable::now();
        }

        return $data;
    }
}
