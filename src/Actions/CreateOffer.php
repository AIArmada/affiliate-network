<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateOffer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateSite $site, array $data): AffiliateOffer
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(OfferStatus::class)],
            'visibility' => ['nullable', Rule::enum(OfferVisibility::class)],
            'source' => ['nullable', 'string', 'in:mirrored,manual'],
            'network_fee_bp' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'rate_base_bp' => ['nullable', 'integer', 'min:0'],
            'rate_fixed_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'cookie_days' => ['nullable', 'integer', 'min:0'],
            'volume_tiers' => ['nullable', 'array'],
            'volume_tiers.*.min_volume_minor' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.rate_bp' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.currency' => ['nullable', 'string', 'max:3'],
            'active_promotions' => ['nullable', 'array'],
            'is_featured' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'landing_url' => ['nullable', 'url', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'restrictions' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'archived_at' => ['nullable', 'date'],
            'external_program_id' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_key' => ['nullable', 'string', 'max:255'],
            'source_checksum' => ['nullable', 'string', 'max:64'],
            'last_synced_at' => ['nullable', 'date'],
        ])->validate();

        if (config('affiliate-network.owner.enabled', false)) {
            $validatedSite = OwnerWriteGuard::findOrFailForOwner(
                AffiliateSite::class,
                (string) $site->getKey(),
                includeGlobal: false,
                message: 'Site is not accessible in the current owner scope.',
            );
        } else {
            $validatedSite = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();
        }

        $validated['site_id'] = (string) $validatedSite->getKey();

        $this->guardPublishedSite($validatedSite, $validated['status'] ?? null);

        $categoryId = $data['category_id'] ?? null;

        if (is_scalar($categoryId) && (string) $categoryId !== '') {
            if (config('affiliate-network.owner.enabled', false)) {
                $validatedCategory = OwnerWriteGuard::findOrFailForOwner(
                    AffiliateOfferCategory::class,
                    (string) $categoryId,
                    includeGlobal: (bool) config('affiliate-network.owner.include_global', false),
                    message: 'Category is not accessible in the current owner scope.',
                );
            } else {
                $validatedCategory = AffiliateOfferCategory::query()->whereKey((string) $categoryId)->firstOrFail();
            }

            $validated['category_id'] = (string) $validatedCategory->getKey();
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (empty($validated['status'])) {
            $validated['status'] = OfferStatus::Draft;
        }

        if (! empty($validated['currency'])) {
            $validated['currency'] = mb_strtoupper((string) $validated['currency']);
        }

        if (array_key_exists('volume_tiers', $validated)) {
            $validated['volume_tiers'] = AffiliateOffer::normalizeVolumeTiers(
                $validated['volume_tiers'],
                $validated['currency'] ?? null,
            );
        }

        // Sync internals are deliberately not fillable; only this action and
        // UpdateOffer may persist them via explicit forceFill.
        $offer = new AffiliateOffer(Arr::except($validated, ['source_checksum', 'last_synced_at']));
        $offer->forceFill(Arr::only($validated, ['source_checksum', 'last_synced_at']));
        $offer->save();

        event(new OfferCreated($offer));

        return $offer;
    }

    private function guardPublishedSite(AffiliateSite $site, mixed $status): void
    {
        $value = $status instanceof OfferStatus ? $status->value : (string) ($status ?? '');

        if ($value === OfferStatus::Published->value && ! $site->isVerified()) {
            throw ValidationException::withMessages([
                'status' => 'Only verified sites can publish offers.',
            ]);
        }
    }
}
