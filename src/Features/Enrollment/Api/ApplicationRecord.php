<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Api;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use Carbon\CarbonImmutable;

final readonly class ApplicationRecord
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $offerId,
        public string $affiliateId,
        public ?string $invitationId,
        public ApplicationStatus $status,
        public ?string $reason,
        public ?string $rejectionReason,
        public ?string $reviewedBy,
        public ?CarbonImmutable $reviewedAt,
        public ?CarbonImmutable $approvedAt,
        public ?CarbonImmutable $rejectedAt,
        public ?CarbonImmutable $revokedAt,
        public ?array $metadata,
    ) {}
}
