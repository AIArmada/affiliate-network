<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\NetworkMembership;

/**
 * Bridge imported offers to their core programs.
 *
 * Offers mirrored from a local program catalog delegate approval to the
 * linked core program; everything here is keyed by program id so the
 * network never touches program models.
 */
interface LinkedProgramBridge
{
    public function join(string $affiliateId, string $programId): NetworkMembership;

    /**
     * @param  array<int, string>  $programIds
     * @return array<string, NetworkMembership> Memberships keyed by program id.
     */
    public function membershipsFor(string $affiliateId, array $programIds): array;

    /**
     * @param  array<int, string>  $programIds
     * @return array<int, string>
     */
    public function existingProgramIds(array $programIds): array;

    /**
     * @return array<int, string> Approved program ids for the affiliate.
     */
    public function approvedProgramIds(string $affiliateId, int $limit = 500): array;
}
