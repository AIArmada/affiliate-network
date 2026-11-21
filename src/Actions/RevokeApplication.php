<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RevokeApplication
{
    public function execute(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return DB::transaction(function () use ($application, $reason, $reviewedBy): AffiliateOfferApplication {
            $application = AffiliateOfferApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status === ApplicationStatus::Revoked) {
                return $application;
            }

            if (! $application->isApproved()) {
                throw new RuntimeException('Only approved applications can be revoked.');
            }

            $application->update([
                'status' => ApplicationStatus::Revoked,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => CarbonImmutable::now(),
                'revoked_at' => CarbonImmutable::now(),
            ]);

            return $application->refresh();
        });
    }
}
