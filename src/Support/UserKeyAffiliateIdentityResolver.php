<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Default affiliate identity for engine-less installs: the app user itself.
 *
 * Network-only hosts have no affiliates engine, so the auth user key
 * doubles as the affiliate id and the user row doubles as the owner.
 * The affiliates package overwrites this binding with its own adapter
 * when installed.
 */
final class UserKeyAffiliateIdentityResolver implements AffiliateIdentityResolver
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

        $key = (new $model)->newQuery()->where('email', $email)->value((new $model)->getKeyName());

        return $key === null ? null : (string) $key;
    }

    private function userByKey(string $affiliateId): ?Model
    {
        $model = $this->userModelClass();

        if ($model === null) {
            return null;
        }

        $user = (new $model)->newQuery()->whereKey($affiliateId)->first();

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

        return new NetworkAffiliate(
            id: (string) $user->getKey(),
            code: (string) $user->getKey(),
            email: is_string($email) ? $email : null,
            ownerType: $user->getMorphClass(),
            ownerId: $user->getKey(),
        );
    }
}
