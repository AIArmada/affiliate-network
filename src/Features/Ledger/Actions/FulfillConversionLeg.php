<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Actions;

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Data\FulfillmentResult;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\ResolveLegOwnerQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FulfillConversionLeg
{
    public function __construct(
        private readonly Fulfillment $fulfillment,
        private readonly SitesApi $sites,
        private readonly ResolveLegOwnerQuery $owners,
    ) {}

    public function execute(string $legId, ?string $reference = null): NetworkConversionLeg
    {
        $preview = NetworkConversionLeg::query()
            ->withoutGlobalScopes()
            ->whereKey($legId)
            ->firstOrFail();

        return OwnerContext::withOwner($this->owners->forLeg($preview), fn (): NetworkConversionLeg => $this->fulfill($legId, $reference));
    }

    private function fulfill(string $legId, ?string $reference): NetworkConversionLeg
    {
        return DB::transaction(function () use ($legId, $reference): NetworkConversionLeg {
            $leg = NetworkConversionLeg::query()
                ->withoutGlobalScopes()
                ->whereKey($legId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leg->fulfilled_at !== null) {
                return $leg;
            }

            if ($leg->status !== LegStatus::Posted) {
                throw new RuntimeException('Only posted legs can be fulfilled.');
            }

            $this->assertPayWhenPaidGate($leg);

            $result = $this->fulfillment->fulfill($leg->toSnapshot(), $reference);

            $this->persistOutcome($leg, $result);

            return $leg->refresh();
        });
    }

    private function assertPayWhenPaidGate(NetworkConversionLeg $leg): void
    {
        $site = $leg->site_id === null ? null : $this->sites->findByIdUnscoped((string) $leg->site_id);

        if ($site === null || ! $site->requireSettledForPayout) {
            return;
        }

        $statement = $leg->statement_id === null
            ? null
            : MerchantStatement::query()->withoutGlobalScopes()->whereKey($leg->statement_id)->first();

        if (! $statement instanceof MerchantStatement || $statement->status !== SettlementStatus::Paid) {
            throw new RuntimeException('Pay-when-paid: conversion leg cannot be fulfilled until its merchant statement is paid.');
        }
    }

    private function persistOutcome(NetworkConversionLeg $leg, FulfillmentResult $result): void
    {
        $metadata = $leg->metadata ?? [];

        if ($result->receipt !== null) {
            $metadata['fulfilled'] = $result->receipt->toArray();

            $leg->forceFill([
                'fulfilled_at' => $result->receipt->fulfilledAt,
                'metadata' => $metadata,
            ])->save();

            return;
        }

        $reason = $result->unfulfillableReason ?? 'unknown';

        if ($reason === 'reconciliation_required') {
            $metadata['reconciliation_required'] = true;
        } else {
            $metadata['unfulfillable'] = $reason;
        }

        $leg->forceFill(['metadata' => $metadata])->save();
    }
}
