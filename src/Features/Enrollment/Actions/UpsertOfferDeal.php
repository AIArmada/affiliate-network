<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class UpsertOfferDeal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(string $offerId, string $affiliateId, array $data): AffiliateOfferDeal
    {
        $validated = Validator::make($data, [
            'rate_base_bp' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'rate_fixed_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'network_fee_bp' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'volume_tiers' => ['sometimes', 'nullable', 'array'],
            'volume_tiers.*.min_volume_minor' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.rate_bp' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.currency' => ['nullable', 'string', 'max:3'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ])->validate();

        if (is_string($validated['currency'] ?? null) && $validated['currency'] !== '') {
            $validated['currency'] = mb_strtoupper((string) $validated['currency']);
        }

        return DB::transaction(function () use ($offerId, $affiliateId, $validated): AffiliateOfferDeal {
            $deal = AffiliateOfferDeal::query()
                ->where('offer_id', $offerId)
                ->where('affiliate_id', $affiliateId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($deal instanceof AffiliateOfferDeal) {
                $deal->update($validated);

                return $deal->refresh();
            }

            return AffiliateOfferDeal::query()->create([
                'offer_id' => $offerId,
                'affiliate_id' => $affiliateId,
                'is_active' => $validated['is_active'] ?? true,
            ] + $validated);
        });
    }
}
