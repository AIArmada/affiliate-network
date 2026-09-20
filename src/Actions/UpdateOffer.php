<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Arr;

final class UpdateOffer
{
    /**
     * Fields an update may touch. Sync identity and internals are included
     * for the catalog importer; unknown keys are dropped.
     *
     * @return array<int, string>
     */
    public static function updatableFields(): array
    {
        return [
            'site_id',
            'category_id',
            'name',
            'slug',
            'description',
            'terms',
            'status',
            'visibility',
            'rate_source',
            'rate_base_bp',
            'rate_fixed_minor',
            'currency',
            'cookie_days',
            'volume_tiers',
            'active_promotions',
            'is_featured',
            'requires_approval',
            'landing_url',
            'restrictions',
            'metadata',
            'starts_at',
            'ends_at',
            'published_at',
            'archived_at',
            'external_program_id',
            'subject_type',
            'subject_key',
            'source_url',
            'source_checksum',
            'last_synced_at',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOffer $offer, array $data): AffiliateOffer
    {
        $offer = AffiliateOffer::query()
            ->whereKey($offer->getKey())
            ->firstOrFail();

        $data = Arr::only($data, self::updatableFields());

        if (! empty($data['currency'])) {
            $data['currency'] = mb_strtoupper((string) $data['currency']);
        }

        $this->guardRelocation($offer, $data);

        // Sync internals are deliberately not fillable; persist them via an
        // explicit forceFill so mass assignment can never touch them.
        $offer->fill(Arr::except($data, ['source_checksum', 'last_synced_at']));
        $offer->forceFill(Arr::only($data, ['source_checksum', 'last_synced_at']));
        $offer->save();

        $fresh = $offer->fresh() ?? $offer;

        event(new OfferUpdated($fresh));

        return $fresh;
    }

    /**
     * Re-validate site/category moves exactly like CreateOffer so an update
     * can never relocate an offer to an inaccessible site or category.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardRelocation(AffiliateOffer $offer, array $data): void
    {
        $ownerEnabled = (bool) config('affiliate-network.owner.enabled', false);

        if (array_key_exists('site_id', $data) && (string) $data['site_id'] !== (string) $offer->site_id) {
            if ($ownerEnabled) {
                OwnerWriteGuard::findOrFailForOwner(
                    AffiliateSite::class,
                    (string) $data['site_id'],
                    includeGlobal: false,
                    message: 'Site is not accessible in the current owner scope.',
                );
            } else {
                AffiliateSite::query()->whereKey((string) $data['site_id'])->firstOrFail();
            }
        }

        if (array_key_exists('category_id', $data) && (string) ($data['category_id'] ?? '') !== (string) ($offer->category_id ?? '')) {
            if ($data['category_id'] === null || (string) $data['category_id'] === '') {
                return;
            }

            if ($ownerEnabled) {
                OwnerWriteGuard::findOrFailForOwner(
                    AffiliateOfferCategory::class,
                    (string) $data['category_id'],
                    includeGlobal: (bool) config('affiliate-network.owner.include_global', false),
                    message: 'Category is not accessible in the current owner scope.',
                );
            } else {
                AffiliateOfferCategory::query()->whereKey((string) $data['category_id'])->firstOrFail();
            }
        }
    }
}
