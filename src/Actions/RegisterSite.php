<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Self-service merchant site registration.
 *
 * The site is created pending and owned by the registering user. Nothing
 * about a pending site is marketplace-visible: offers cannot publish,
 * links do not redirect, and postbacks are refused until a network admin
 * verifies the site. Re-registering a domain the same owner had rejected
 * resubmits it for review instead of failing as a duplicate.
 */
final class RegisterSite
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Model $owner, array $data): AffiliateSite
    {
        if (isset($data['domain']) && is_string($data['domain'])) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/',
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'catalog_url' => ['nullable', 'url', 'max:2048'],
        ], [
            'domain.regex' => 'The domain must be a valid hostname.',
        ])->validate();

        return OwnerContext::withOwner($owner, function () use ($owner, $validated): AffiliateSite {
            // Unscoped: any live or trashed row holding the domain blocks the
            // DB unique index, so the friendly error must see them all.
            $existing = AffiliateSite::query()
                ->withoutGlobalScopes()
                ->where('domain', (string) $validated['domain'])
                ->first();

            if ($existing instanceof AffiliateSite) {
                if ($existing->status !== AffiliateSite::STATUS_REJECTED || ! $this->owns($owner, $existing)) {
                    throw ValidationException::withMessages([
                        'domain' => 'This domain is already registered.',
                    ]);
                }

                $existing->update([
                    'name' => mb_trim((string) $validated['name']),
                    'description' => isset($validated['description']) ? mb_trim((string) $validated['description']) : null,
                    'status' => AffiliateSite::STATUS_PENDING,
                    'verification_token' => Str::random(40),
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
                    'status' => AffiliateSite::STATUS_PENDING,
                    'verification_token' => Str::random(40),
                    'catalog_url' => $validated['catalog_url'] ?? null,
                ]);
            } catch (QueryException $exception) {
                // Concurrent double-register: the unique(domain) row already
                // exists, so answer friendly instead of 500ing.
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

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
