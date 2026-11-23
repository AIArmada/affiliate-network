<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\ResolveLegOwnerQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ConfirmConversionLeg
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly ResolveLegOwnerQuery $owners,
    ) {}

    /**
     * @param  array<string, mixed>  $decisionMetadata
     */
    public function execute(NetworkConversionLeg $leg, array $decisionMetadata = []): NetworkConversionLeg
    {
        return OwnerContext::withOwner($this->owners->forLeg($leg), fn (): NetworkConversionLeg => $this->confirm($leg, $decisionMetadata));
    }

    /**
     * @param  array<string, mixed>  $decisionMetadata
     */
    private function confirm(NetworkConversionLeg $leg, array $decisionMetadata): NetworkConversionLeg
    {
        return DB::transaction(function () use ($leg, $decisionMetadata): NetworkConversionLeg {
            $locked = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->whereKey($leg->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== LegStatus::Provisional) {
                return $locked;
            }

            $offer = $this->catalog->findOfferUnscoped((string) $locked->offer_id);
            $site = $locked->site_id === null ? null : $this->sites->findByIdUnscoped((string) $locked->site_id);

            $lockingDays = $this->lockingDays($offer?->lockingDays, $site?->defaultLockingDays);
            $occurredAt = $locked->occurred_at ?? CarbonImmutable::now();
            $metadata = array_merge($locked->metadata ?? [], $decisionMetadata);

            if ($lockingDays > 0) {
                $locked->forceFill([
                    'status' => LegStatus::Pending,
                    'locks_at' => $occurredAt->addDays($lockingDays),
                    'locked_at' => null,
                    'metadata' => $metadata === [] ? null : $metadata,
                ])->save();

                return $locked;
            }

            $locked->forceFill([
                'status' => LegStatus::Posted,
                'locks_at' => null,
                'locked_at' => CarbonImmutable::now(),
                'metadata' => $metadata === [] ? null : $metadata,
            ])->save();

            return $locked;
        });
    }

    private function lockingDays(?int $offerLockingDays, ?int $siteLockingDays): int
    {
        if ($offerLockingDays !== null && $offerLockingDays > 0) {
            return $offerLockingDays;
        }

        if ($siteLockingDays !== null && $siteLockingDays > 0) {
            return $siteLockingDays;
        }

        return max(0, (int) config('affiliate-network.locking.default_days', 0));
    }
}
