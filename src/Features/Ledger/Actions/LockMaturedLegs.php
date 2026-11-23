<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use AIArmada\AffiliateNetwork\Events\ConversionLocked;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\ResolveLegOwnerQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class LockMaturedLegs
{
    public function __construct(
        private readonly SitesApi $sites,
        private readonly Fulfillment $fulfillment,
        private readonly FulfillConversionLeg $fulfillConversionLeg,
        private readonly ResolveLegOwnerQuery $owners,
    ) {}

    /**
     * @return list<string>
     */
    public function execute(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now();

        $ids = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('status', LegStatus::Pending)
            ->whereNotNull('locks_at')
            ->where('locks_at', '<=', $asOf)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $locked = [];

        foreach ($ids as $id) {
            $result = $this->lockOne($id);

            if ($result !== null) {
                $locked[] = $result;
            }
        }

        return $locked;
    }

    private function lockOne(string $legId): ?string
    {
        $preview = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereKey($legId)
            ->first();

        if (! $preview instanceof NetworkConversionLeg) {
            return null;
        }

        $owner = $this->owners->forLeg($preview);

        return OwnerContext::withOwner($owner, fn (): ?string => $this->lockInOwnerContext($legId));
    }

    private function lockInOwnerContext(string $legId): ?string
    {
        $snapshot = DB::transaction(function () use ($legId): ?NetworkLegSnapshot {
            $leg = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->whereKey($legId)
                ->lockForUpdate()
                ->first();

            if (! $leg instanceof NetworkConversionLeg || $leg->status !== LegStatus::Pending) {
                return null;
            }

            $leg->forceFill([
                'status' => LegStatus::Posted,
                'locked_at' => CarbonImmutable::now(),
            ])->save();

            return $leg->toSnapshot();
        });

        if ($snapshot === null) {
            return null;
        }

        $this->fulfillIfEligible($snapshot->id);

        $fresh = NetworkConversionLeg::query()->withoutGlobalScopes()->whereKey($snapshot->id)->first();
        $finalSnapshot = $fresh instanceof NetworkConversionLeg ? $fresh->toSnapshot() : $snapshot;

        event(new ConversionLocked($finalSnapshot));

        return $snapshot->id;
    }

    private function fulfillIfEligible(string $legId): void
    {
        if (! $this->fulfillment->fulfillsAutomatically()) {
            return;
        }

        $leg = NetworkConversionLeg::query()->withoutGlobalScopes()->whereKey($legId)->first();

        if (! $leg instanceof NetworkConversionLeg) {
            return;
        }

        $site = $leg->site_id === null ? null : $this->sites->findByIdUnscoped((string) $leg->site_id);

        if ($site !== null && $site->requireSettledForPayout) {
            return;
        }

        $this->fulfillConversionLeg->execute($legId);
    }
}
