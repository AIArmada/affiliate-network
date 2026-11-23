<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Queries;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ResolveLegOwnerQuery
{
    public function __construct(
        private readonly AffiliateIdentityResolver $identities,
        private readonly SitesApi $sites,
    ) {}

    public function forLeg(NetworkConversionLeg $leg): ?Model
    {
        $identity = $this->identities->find((string) $leg->affiliate_id);

        if ($identity !== null) {
            return $this->fromTuple($identity->ownerType, $identity->ownerId);
        }

        if ($leg->site_id === null || $leg->site_id === '') {
            return null;
        }

        $site = $this->sites->findByIdUnscoped((string) $leg->site_id);

        return $site === null ? null : $this->fromTuple($site->ownerType, $site->ownerId);
    }

    private function fromTuple(?string $ownerType, string | int | null $ownerId): ?Model
    {
        if ($ownerType === null || $ownerType === '' || $ownerId === null || $ownerId === '') {
            return null;
        }

        try {
            return OwnerContext::fromTypeAndId($ownerType, $ownerId);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
