<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\PublicHandle;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class UserKeyIdentityDriver implements AffiliateIdentityResolver
{
    public function find(string $affiliateId): ?NetworkAffiliate
    {
        $user = $this->userByKey($affiliateId);

        return $user instanceof Model ? $this->toNetworkAffiliate($user) : null;
    }

    public function findAccessible(string $affiliateId): ?NetworkAffiliate
    {
        $affiliate = $this->find($affiliateId);

        if ($affiliate === null) {
            return null;
        }

        $scope = OwnerContext::resolve();

        if ($scope === null) {
            return $affiliate;
        }

        $user = $this->userByKey($affiliateId);

        if (! $user instanceof Model) {
            return null;
        }

        $sameOwner = $scope->getMorphClass() === $user->getMorphClass()
            && (string) $scope->getKey() === (string) $user->getKey();

        return $sameOwner ? $affiliate : null;
    }

    public function findIdForVerifiedEmail(string $email): ?string
    {
        $model = $this->userModelClass();

        if ($model === null) {
            return null;
        }

        $instance = new $model;
        $key = $instance->newQuery()->where('email', $email)->value($instance->getKeyName());

        return $key === null ? null : (string) $key;
    }

    public function requiresMerchantProfile(): bool
    {
        return false;
    }

    public function hasMerchantProfile(string $affiliateId): bool
    {
        return false;
    }

    private function userByKey(string $affiliateId): ?Model
    {
        $model = $this->userModelClass();

        if ($model === null) {
            return null;
        }

        $instance = new $model;

        if ($instance->getKeyType() === 'int' && ! ctype_digit($affiliateId)) {
            return null;
        }

        if (in_array(HasUuids::class, class_uses_recursive($model), true) && ! Str::isUuid($affiliateId)) {
            return null;
        }

        $user = $instance->newQuery()->whereKey($affiliateId)->first();

        return $user instanceof Model ? $user : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function userModelClass(): ?string
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model;
    }

    private function toNetworkAffiliate(Model $user): NetworkAffiliate
    {
        $email = $user->getAttribute('email');
        $handle = $user->hasAttribute('handle') ? $user->getAttribute('handle') : null;
        $handle ??= PublicHandle::forIdentity($user->getMorphClass() . ':' . $user->getKey());
        $handle = is_string($handle) ? PublicHandle::normalize($handle) : $handle;
        Validator::make(['handle' => $handle], ['handle' => PublicHandle::rules()])->validate();

        return new NetworkAffiliate(
            id: (string) $user->getKey(),
            code: (string) $user->getKey(),
            handle: is_string($handle) ? $handle : '',
            email: is_string($email) ? $email : null,
            ownerType: $user->getMorphClass(),
            ownerId: $user->getKey(),
            userId: (string) $user->getKey(),
        );
    }
}
