<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\ResolveLegOwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\DB;

final class SupersedeConversionLeg
{
    public function __construct(
        private readonly ResolveLegOwnerQuery $owners,
    ) {}

    /**
     * @param  array<string, mixed>  $decisionMetadata
     */
    public function execute(NetworkConversionLeg $leg, string $reason, array $decisionMetadata = []): NetworkConversionLeg
    {
        return OwnerContext::withOwner($this->owners->forLeg($leg), fn (): NetworkConversionLeg => $this->supersede($leg, $reason, $decisionMetadata));
    }

    /**
     * @param  array<string, mixed>  $decisionMetadata
     */
    private function supersede(NetworkConversionLeg $leg, string $reason, array $decisionMetadata): NetworkConversionLeg
    {
        return DB::transaction(function () use ($leg, $reason, $decisionMetadata): NetworkConversionLeg {
            $locked = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->whereKey($leg->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== LegStatus::Provisional) {
                return $locked;
            }

            $locked->forceFill([
                'status' => LegStatus::Superseded,
                'metadata' => array_merge(
                    $locked->metadata ?? [],
                    ['superseded_reason' => $reason],
                    $decisionMetadata,
                ),
            ])->save();

            return $locked;
        });
    }
}
