<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateCreative
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOffer $offer, array $data): AffiliateOfferCreative
    {
        $offer = AffiliateOffer::query()->whereKey($offer->getKey())->firstOrFail();

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CreativeType::class)],
            'description' => ['nullable', 'string'],
            'destination_url' => ['nullable', 'url:http,https', 'max:2048'],
            'width' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'height' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'html_code' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ])->validate();

        return $offer->creatives()->create($validated + [
            'external_creative_id' => null,
            'source_asset_url' => null,
        ]);
    }
}
