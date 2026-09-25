<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Adapters\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Support\UserKeyAffiliateIdentityResolver;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Data\MerchantAffiliate;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Network identity with merchant recognition.
 *
 * Merchant affiliate rows resolve first (for reporting and linkage),
 * ids without one fall back to the host user. Nothing is required or
 * provisioned on the merchant side — network identity stays sufficient.
 */
final class AffiliatesIdentityReader implements AffiliateIdentityResolver
{
    public function __construct(
        private readonly MerchantIdentity $merchants,
        private readonly UserKeyAffiliateIdentityResolver $users,
    ) {}

    public function find(string $affiliateId): ?NetworkAffiliate
    {
        $merchant = $this->merchants->find($affiliateId);

        if ($merchant !== null) {
            return self::toNetworkAffiliate($merchant);
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
            email: $affiliate->contact_email,
            ownerType: $affiliate->owner_type,
            ownerId: $affiliate->owner_id,
        );
    }

    public function findIdForVerifiedEmail(string $email): ?string
    {
        return $this->merchants->findIdForEmail($email)
            ?? $this->users->findIdForVerifiedEmail($email);
    }

    private static function toNetworkAffiliate(MerchantAffiliate $merchant): NetworkAffiliate
    {
        return new NetworkAffiliate(
            id: $merchant->id,
            code: $merchant->code,
            email: $merchant->email,
            ownerType: $merchant->ownerType,
            ownerId: $merchant->ownerId,
        );
    }
}
