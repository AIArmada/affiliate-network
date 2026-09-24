<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;

/**
 * Resolve affiliate identity for the network.
 *
 * The network identifies affiliates by opaque id; only the adapter knows
 * what an affiliate is. find() is an explicit global lookup (no scope
 * enforcement) for entering owner contexts; findAccessible() additionally
 * requires the affiliate to be visible in the current owner scope.
 */
interface AffiliateIdentityResolver
{
    public function find(string $affiliateId): ?NetworkAffiliate;

    public function findAccessible(string $affiliateId): ?NetworkAffiliate;

    /**
     * Resolve the affiliate id for a verified account email.
     *
     * Callers must only pass emails they have verified; the adapter
     * matches on identity, it does not verify ownership.
     */
    public function findIdForVerifiedEmail(string $email): ?string;
}
