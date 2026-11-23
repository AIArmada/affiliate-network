<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Api;

use Carbon\CarbonImmutable;

final readonly class InvitationRecord
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $offerId,
        public ?string $affiliateId,
        public string $token,
        public ?string $invitedBy,
        public bool $autoApprove,
        public ?CarbonImmutable $expiresAt,
        public ?CarbonImmutable $acceptedAt,
        public ?CarbonImmutable $revokedAt,
        public ?array $metadata,
    ) {}
}
