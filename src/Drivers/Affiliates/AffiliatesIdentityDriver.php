<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Drivers\UserKeyIdentityDriver;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Data\MerchantAffiliate;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

final class AffiliatesIdentityDriver implements AffiliateIdentityResolver
{
    public function __construct(
        private readonly MerchantIdentity $merchants,
        private readonly UserKeyIdentityDriver $users,
    ) {}

    public function find(string $affiliateId): ?NetworkAffiliate
    {
        $merchant = $this->merchants->find($affiliateId);

        if ($merchant !== null) {
            return $this->toNetworkAffiliate($merchant);
        }

        return $this->users->find($affiliateId);
    }

    public function findAccessible(string $affiliateId): ?NetworkAffiliate
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $this->find($affiliateId);
        }

        try {
            $affiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $affiliateId,
                includeGlobal: false,
                message: 'Affiliate is not accessible in the current owner scope.',
            );
        } catch (AuthorizationException) {
            return null;
        } catch (ModelNotFoundException) {
            return $this->users->findAccessible($affiliateId);
        }

        if (! $affiliate instanceof Affiliate) {
            return null;
        }

        return new NetworkAffiliate(
            id: (string) $affiliate->getKey(),
            code: (string) $affiliate->code,
            handle: (string) $affiliate->handle,
            email: $affiliate->contact_email,
            ownerType: $affiliate->owner_type,
            ownerId: $affiliate->owner_id,
        );
    }

    public function requiresMerchantProfile(): bool
    {
        return true;
    }

    public function hasMerchantProfile(string $affiliateId): bool
    {
        return $this->merchants->find($affiliateId) !== null;
    }

    public function findIdForVerifiedEmail(string $email): ?string
    {
        return $this->merchants->findIdForEmail($email)
            ?? $this->users->findIdForVerifiedEmail($email);
    }

    private function toNetworkAffiliate(MerchantAffiliate $merchant): NetworkAffiliate
    {
        $userModel = config('auth.providers.users.model');
        $userId = null;

        if (is_string($merchant->ownerType)
            && $merchant->ownerId !== null
            && is_string($userModel)
            && $this->ownerResolvesToUserModel($merchant->ownerType, $userModel)) {
            $userId = (string) $merchant->ownerId;
        } elseif (is_string($merchant->email) && $merchant->email !== '') {
            $userId = $this->users->findIdForVerifiedEmail($merchant->email);
        }

        return new NetworkAffiliate(
            id: $merchant->id,
            code: $merchant->code,
            handle: $merchant->handle,
            email: $merchant->email,
            ownerType: $merchant->ownerType,
            ownerId: $merchant->ownerId,
            userId: $userId,
        );
    }

    private function ownerResolvesToUserModel(string $ownerType, string $userModel): bool
    {
        $resolved = Relation::getMorphedModel($ownerType) ?? $ownerType;

        return is_a($resolved, $userModel, true);
    }
}
