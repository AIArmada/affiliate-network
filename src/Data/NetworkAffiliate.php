<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The network's view of an affiliate: identity plus owner coordinates.
 *
 * Never an affiliates model — the network only ever handles the id and
 * the coordinates it needs to enter the affiliate's owner context.
 */
final readonly class NetworkAffiliate
{
    public function __construct(
        public string $id,
        public string $code,
        public ?string $email = null,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}

    public function owner(): ?Model
    {
        return OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId);
    }
}
