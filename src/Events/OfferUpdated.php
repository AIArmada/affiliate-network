<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class OfferUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $offerId,
        public string $siteId,
        public string $name,
        public string $slug,
        public string $status,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}
}
