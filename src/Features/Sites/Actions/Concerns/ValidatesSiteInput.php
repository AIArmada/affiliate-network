<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions\Concerns;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Platform\DomainHostValidator;
use Carbon\CarbonImmutable;
use Closure;
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
            'domain' => [
                'required',
                'string',
                'max:255',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! DomainHostValidator::isValidHostname($value)) {
                        $fail('The domain must be a valid hostname.');
                    }
                },
                $domainIsUnique,
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(SiteStatus::class)],
            'verification_method' => ['nullable', 'string', 'in:dns,meta_tag,file'],
            'verified_at' => ['nullable', 'date'],
            'catalog_url' => ['nullable', 'url:http,https', 'max:255'],
            'catalog_token' => ['nullable', 'string', 'max:512'],
            'default_locking_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'prepaid_balance_minor' => ['nullable', 'integer'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
            'monthly_spend_cap_minor' => ['nullable', 'integer', 'min:0'],
            'require_settled_for_payout' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
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

        if (is_string($data['domain'] ?? null)) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        if (is_string($data['budget_currency'] ?? null)) {
            $data['budget_currency'] = mb_strtoupper(mb_trim($data['budget_currency']));
        }

        return $data;
    }

    private static function resolveStatus(mixed $status): SiteStatus
    {
        if ($status instanceof SiteStatus) {
            return $status;
        }

        return is_string($status) && $status !== ''
            ? SiteStatus::from($status)
            : SiteStatus::Pending;
    }
}
