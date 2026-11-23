<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Platform\DomainHostValidator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegisterSite
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Model $owner, array $data): AffiliateSite
    {
        if (is_string($data['domain'] ?? null)) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        $validated = Validator::make($data, [
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
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'catalog_url' => ['nullable', 'url:http,https', 'max:255'],
        ])->validate();

        return OwnerContext::withOwner($owner, function () use ($owner, $validated): AffiliateSite {
            $existing = AffiliateSite::query()
                ->withoutGlobalScopes()
                ->where('domain', (string) $validated['domain'])
                ->first();

            if ($existing instanceof AffiliateSite) {
                if (! $existing->isRejected() || ! $this->owns($owner, $existing)) {
                    throw ValidationException::withMessages([
                        'domain' => 'This domain is already registered.',
                    ]);
                }

                $existing->update([
                    'name' => mb_trim((string) $validated['name']),
                    'description' => isset($validated['description']) ? mb_trim((string) $validated['description']) : null,
                    'status' => SiteStatus::Pending,
                    'verified_at' => null,
                    'verification_method' => null,
                    'verification_token' => $this->freshToken(),
                    'catalog_url' => $validated['catalog_url'] ?? null,
                ]);

                return $existing->refresh();
            }

            try {
                return AffiliateSite::query()->create([
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'name' => mb_trim((string) $validated['name']),
                    'domain' => (string) $validated['domain'],
                    'description' => isset($validated['description']) ? mb_trim((string) $validated['description']) : null,
                    'status' => SiteStatus::Pending,
                    'verification_token' => $this->freshToken(),
                    'catalog_url' => $validated['catalog_url'] ?? null,
                ]);
            } catch (QueryException $exception) {
                if (! self::isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'domain' => 'This domain is already registered.',
                ]);
            }
        });
    }

    private function owns(Model $owner, AffiliateSite $site): bool
    {
        return $site->owner_type === $owner->getMorphClass()
            && (string) $site->owner_id === (string) $owner->getKey();
    }

    private function freshToken(): string
    {
        return 'affiliatenetwork-verify-' . Str::random(32);
    }

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
