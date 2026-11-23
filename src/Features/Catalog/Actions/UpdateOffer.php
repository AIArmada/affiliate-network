<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateOffer
{
    public function __construct(
        private readonly SitesApi $sites,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOffer $offer, array $data, bool $fromCatalogSync = false): AffiliateOffer
    {
        $offer = AffiliateOffer::query()->whereKey($offer->getKey())->firstOrFail();

        $validated = Validator::make($data, [
            'site_id' => ['sometimes', 'required', 'string', 'max:36'],
            'category_id' => ['sometimes', 'nullable', 'string', 'max:36'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'nullable', Rule::enum(OfferStatus::class)],
            'visibility' => ['sometimes', 'nullable', Rule::enum(OfferVisibility::class)],
            'source' => ['sometimes', 'nullable', Rule::enum(OfferSource::class)],
            'network_fee_bp' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'rate_base_bp' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'rate_fixed_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:3'],
            'cookie_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'locking_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'daily_conversion_cap' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'monthly_conversion_cap' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'total_conversion_cap' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'per_affiliate_conversion_cap' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'monthly_payout_cap_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'total_payout_cap_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'volume_tiers' => ['sometimes', 'nullable', 'array'],
            'volume_tiers.*.min_volume_minor' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.rate_bp' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.currency' => ['nullable', 'string', 'max:3'],
            'active_promotions' => ['sometimes', 'nullable', 'array'],
            'is_featured' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'landing_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'source_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'fallback_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'fallback_offer_id' => ['sometimes', 'nullable', 'string', 'max:36'],
            'allow_deep_linking' => ['sometimes', 'boolean'],
            'event_rates' => ['sometimes', 'nullable', 'array'],
            'restrictions' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'archived_at' => ['sometimes', 'nullable', 'date'],
            'external_program_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_checksum' => ['sometimes', 'nullable', 'string', 'max:64'],
            'creatives_checksum' => ['sometimes', 'nullable', 'string', 'max:64'],
            'last_synced_at' => ['sometimes', 'nullable', 'date'],
        ])->validate();

        $effectiveSiteId = array_key_exists('site_id', $validated)
            ? (string) $validated['site_id']
            : (string) $offer->site_id;

        if (array_key_exists('site_id', $validated) && $effectiveSiteId !== (string) $offer->site_id) {
            if ($this->sites->findByIdUnscoped($effectiveSiteId) === null) {
                throw ValidationException::withMessages([
                    'site_id' => 'Site is not accessible in the current owner scope.',
                ]);
            }
        }

        if (array_key_exists('category_id', $validated)
            && is_string($validated['category_id'])
            && $validated['category_id'] !== '') {
            AffiliateOfferCategory::query()->whereKey($validated['category_id'])->firstOrFail();
        }

        $statusProvided = array_key_exists('status', $validated);
        $status = $this->resolveStatus($validated['status'] ?? $offer->status);

        if ($status === OfferStatus::Published && ! $this->sites->isVerified($effectiveSiteId)) {
            throw ValidationException::withMessages([
                'status' => 'Only verified sites can publish offers.',
            ]);
        }

        if (! $fromCatalogSync) {
            if (array_key_exists('source', $validated)) {
                if ($this->resolveSource($validated['source']) === OfferSource::Mirrored) {
                    unset($validated['source_checksum']);
                }
            } elseif ($offer->source === OfferSource::Mirrored
                && array_intersect(array_keys($validated), AffiliateOffer::rateBlockColumns()) !== []) {
                $validated['source'] = OfferSource::Manual;
            }
        }

        $validated['status'] = $status;

        if ($statusProvided) {
            if ($status === OfferStatus::Published) {
                $validated['published_at'] ??= $offer->published_at ?? CarbonImmutable::now();
                $validated['archived_at'] = null;
            } elseif ($status === OfferStatus::Archived) {
                $validated['archived_at'] ??= $offer->archived_at ?? CarbonImmutable::now();
            }
        }

        if (is_string($validated['currency'] ?? null) && $validated['currency'] !== '') {
            $validated['currency'] = mb_strtoupper((string) $validated['currency']);
        }

        if (array_key_exists('volume_tiers', $validated)) {
            $validated['volume_tiers'] = AffiliateOffer::normalizeVolumeTiers(
                is_array($validated['volume_tiers']) ? $validated['volume_tiers'] : null,
                is_string($validated['currency'] ?? null) ? $validated['currency'] : $offer->currency,
            );
        }

        $syncFields = Arr::only($validated, ['source_checksum', 'creatives_checksum', 'last_synced_at']);
        $offer->fill(Arr::except($validated, array_keys($syncFields)));
        $offer->forceFill($syncFields);
        $offer->save();

        $fresh = $offer->fresh() ?? $offer;

        event(new OfferUpdated(
            offerId: (string) $fresh->getKey(),
            siteId: (string) $fresh->site_id,
            name: (string) $fresh->name,
            slug: (string) $fresh->slug,
            status: $status->value,
            ownerType: null,
            ownerId: null,
        ));

        return $fresh;
    }

    private function resolveStatus(mixed $status): OfferStatus
    {
        if ($status instanceof OfferStatus) {
            return $status;
        }

        return is_string($status) && $status !== ''
            ? OfferStatus::from($status)
            : OfferStatus::Draft;
    }

    private function resolveSource(mixed $source): OfferSource
    {
        if ($source instanceof OfferSource) {
            return $source;
        }

        return OfferSource::from((string) $source);
    }
}
