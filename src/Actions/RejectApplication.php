<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RejectApplication
{
    public function execute(AffiliateOfferApplication $application, string $reason, ?string $reviewedBy = null): AffiliateOfferApplication
    {
        return DB::transaction(function () use ($application, $reason, $reviewedBy): AffiliateOfferApplication {
            $application = AffiliateOfferApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status === ApplicationStatus::Rejected) {
                return $application;
            }

            if (! $application->isPending()) {
                throw new RuntimeException('Only pending applications can be rejected.');
            }

            $application->update([
                'status' => ApplicationStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => CarbonImmutable::now(),
                'rejected_at' => CarbonImmutable::now(),
            ]);

            return $application->refresh();
        });
    }
}
