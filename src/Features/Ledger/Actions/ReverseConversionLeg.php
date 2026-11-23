<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Events\ConversionReversed;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReverseConversionLeg
{
    public function __construct(
        private readonly SitesApi $sites,
        private readonly AttributionApi $attribution,
        private readonly AffiliateIdentityResolver $identities,
        private readonly ?NetworkLedger $ledger = null,
    ) {}

    public function execute(string $legId, string $reason): NetworkConversionLeg
    {
        $preview = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereKey($legId)
            ->firstOrFail();

        $identity = $this->identities->find((string) $preview->affiliate_id);
        $owner = $identity === null
            ? null
            : OwnerContext::fromTypeAndId($identity->ownerType, $identity->ownerId);

        return OwnerContext::withOwner($owner, fn (): NetworkConversionLeg => $this->reverse($legId, $reason));
    }

    private function reverse(string $legId, string $reason): NetworkConversionLeg
    {
        [$reversal, $original] = DB::transaction(function () use ($legId, $reason): array {
            $locked = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->whereKey($legId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->parent_leg_id !== null && $locked->status === LegStatus::Reversed) {
                return [$locked, $locked];
            }

            if ($locked->status === LegStatus::Reversed) {
                $companion = $this->findCompanion($locked);

                return [$companion, $locked];
            }

            if (in_array($locked->status, [LegStatus::Provisional, LegStatus::Superseded], true)) {
                throw new InvalidArgumentException('Only pending or posted conversions can be reversed.');
            }

            $locked->forceFill(['status' => LegStatus::Reversed])->save();

            $companion = NetworkConversionLeg::create([
                'link_id' => $locked->link_id,
                'offer_id' => $locked->offer_id,
                'site_id' => $locked->site_id,
                'affiliate_id' => $locked->affiliate_id,
                'link_code' => $locked->link_code,
                'idempotency_key' => 'rev:' . $locked->getKey(),
                'parent_leg_id' => $locked->getKey(),
                'revenue_minor' => 0,
                'revenue_currency' => $locked->revenue_currency,
                'commission_minor' => -1 * (int) $locked->commission_minor,
                'commission_currency' => $locked->commission_currency,
                'fee_minor' => -1 * (int) $locked->fee_minor,
                'fee_bp' => $locked->fee_bp,
                'payout_minor' => -1 * (int) $locked->payout_minor,
                'tier_rate_bp' => $locked->tier_rate_bp,
                'tier_min_volume_minor' => $locked->tier_min_volume_minor,
                'external_reference' => $locked->external_reference,
                'event_type' => $locked->event_type,
                'status' => LegStatus::Reversed,
                'coupon_code' => $locked->coupon_code,
                'sub_id' => $locked->sub_id,
                'sub_id_2' => $locked->sub_id_2,
                'sub_id_3' => $locked->sub_id_3,
                'metadata' => ['reverses_id' => (string) $locked->getKey(), 'reversal_reason' => $reason],
                'occurred_at' => CarbonImmutable::now(),
            ]);

            if ($locked->link_id !== null) {
                $this->attribution->recordLinkCounters((string) $locked->link_id, -1 * (int) $locked->revenue_minor, -1);
            }

            $site = $locked->site_id === null ? null : $this->sites->findByIdUnscoped((string) $locked->site_id);

            if ($site !== null && $site->prepaidBalanceMinor !== null && (int) $locked->commission_minor > 0) {
                $this->sites->adjustPrepaidBalance((string) $site->id, (int) $locked->commission_minor);
            }

            $this->voidLedgerPosting($locked, $reason);

            return [$companion, $locked];
        });

        event(new ConversionReversed($original->toSnapshot(), $reversal->toSnapshot(), $reason));

        return $reversal;
    }

    private function findCompanion(NetworkConversionLeg $leg): NetworkConversionLeg
    {
        return NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->where('idempotency_key', 'rev:' . $leg->getKey())
            ->firstOrFail();
    }

    private function voidLedgerPosting(NetworkConversionLeg $leg, string $reason): void
    {
        if ($this->ledger === null || $leg->link_id === null) {
            return;
        }

        $this->ledger->voidPosting((string) $leg->link_id, (string) $leg->external_reference, $reason);
    }
}
