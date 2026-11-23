<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ApplicationApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $applicationId,
        public string $offerId,
        public string $offerName,
        public string $siteId,
        public string $affiliateId,
        public string $status,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}
}
