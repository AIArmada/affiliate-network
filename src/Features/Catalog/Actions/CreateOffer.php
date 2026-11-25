<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateOffer
{
    public function __construct(
        private readonly SitesApi $sites,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SiteRecord $site, array $data): AffiliateOffer
    {
        $validated = Validator::make($data, [
            'category_id' => ['nullable', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(OfferStatus::class)],
            'visibility' => ['nullable', Rule::enum(OfferVisibility::class)],
            'source' => ['nullable', Rule::enum(OfferSource::class)],
            'network_fee_bp' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'rate_base_bp' => ['nullable', 'integer', 'min:0'],
            'rate_fixed_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'cookie_days' => ['nullable', 'integer', 'min:0'],
            'locking_days' => ['nullable', 'integer', 'min:0'],
            'daily_conversion_cap' => ['nullable', 'integer', 'min:0'],
            'monthly_conversion_cap' => ['nullable', 'integer', 'min:0'],
            'total_conversion_cap' => ['nullable', 'integer', 'min:0'],
            'per_affiliate_conversion_cap' => ['nullable', 'integer', 'min:0'],
            'monthly_payout_cap_minor' => ['nullable', 'integer', 'min:0'],
            'total_payout_cap_minor' => ['nullable', 'integer', 'min:0'],
            'volume_tiers' => ['nullable', 'array'],
            'volume_tiers.*.min_volume_minor' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.rate_bp' => ['required', 'integer', 'min:0'],
            'volume_tiers.*.currency' => ['nullable', 'string', 'max:3'],
            'active_promotions' => ['nullable', 'array'],
            'is_featured' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'landing_url' => ['nullable', 'url:http,https', 'max:500'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'fallback_url' => ['nullable', 'url:http,https', 'max:2048'],
            'fallback_offer_id' => ['nullable', 'string', 'max:36'],
            'allow_deep_linking' => ['nullable', 'boolean'],
            'event_rates' => ['nullable', 'array'],
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
            'creatives_checksum' => ['nullable', 'string', 'max:64'],
            'last_synced_at' => ['nullable', 'date'],
        ])->validate();

        $validated['locking_days'] = (int) ($validated['locking_days'] ?? 0);

        $categoryId = $validated['category_id'] ?? null;

        if (is_string($categoryId) && $categoryId !== '') {
            AffiliateOfferCategory::query()->whereKey($categoryId)->firstOrFail();
        } else {
            unset($validated['category_id']);
        }

        $validated['site_id'] = $site->id;

        $status = $this->resolveStatus($validated['status'] ?? null);

        if ($status === OfferStatus::Published && ! $this->sites->isVerified($site->id)) {
            throw ValidationException::withMessages([
                'status' => 'Only verified sites can publish offers.',
            ]);
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug((string) $validated['name']);
        }

        $validated['status'] = $status;

        if ($status === OfferStatus::Published) {
            $validated['published_at'] ??= CarbonImmutable::now();
            $validated['archived_at'] = null;
        } elseif ($status === OfferStatus::Archived) {
            $validated['archived_at'] ??= CarbonImmutable::now();
        }

        if (is_string($validated['currency'] ?? null) && $validated['currency'] !== '') {
            $validated['currency'] = mb_strtoupper((string) $validated['currency']);
        }

        if (array_key_exists('volume_tiers', $validated)) {
            $validated['volume_tiers'] = AffiliateOffer::normalizeVolumeTiers(
                is_array($validated['volume_tiers']) ? $validated['volume_tiers'] : null,
                is_string($validated['currency'] ?? null) ? $validated['currency'] : null,
            );
        }

        $syncFields = Arr::only($validated, ['source_checksum', 'creatives_checksum', 'last_synced_at']);

        $offer = new AffiliateOffer(Arr::except($validated, array_keys($syncFields)));
        $offer->forceFill($syncFields);
        $offer->save();

        event(new OfferCreated(
            offerId: (string) $offer->getKey(),
            siteId: $site->id,
            name: (string) $offer->name,
            slug: (string) $offer->slug,
            status: $status->value,
            ownerType: $site->ownerType,
            ownerId: $site->ownerId,
        ));

        return $offer;
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
}
