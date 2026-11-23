<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdjustMerchantPrepaidBalance
{
    public function execute(string $siteId, int $deltaMinor): int
    {
        $preview = AffiliateSite::query()
            ->withoutGlobalScopes()
            ->whereKey($siteId)
            ->firstOrFail();

        $owner = $this->ownerFor($preview);

        return OwnerContext::withOwner($owner, fn (): int => $this->adjust($siteId, $deltaMinor));
    }

    private function adjust(string $siteId, int $deltaMinor): int
    {
        return DB::transaction(function () use ($siteId, $deltaMinor): int {
            $site = AffiliateSite::query()
                ->withoutGlobalScopes()
                ->whereKey($siteId)
                ->lockForUpdate()
                ->firstOrFail();

            $balance = (int) ($site->prepaid_balance_minor ?? 0) + $deltaMinor;

            $site->update(['prepaid_balance_minor' => $balance]);

            return $balance;
        });
    }

    private function ownerFor(AffiliateSite $site): ?Model
    {
        if ($site->owner_type === null || $site->owner_type === '' || $site->owner_id === null || $site->owner_id === '') {
            return null;
        }

        try {
            return OwnerContext::fromTypeAndId($site->owner_type, $site->owner_id);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
