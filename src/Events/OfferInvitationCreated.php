<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class OfferInvitationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $invitationId,
        public string $offerId,
        public ?string $affiliateId,
        public string $token,
    ) {}
}
