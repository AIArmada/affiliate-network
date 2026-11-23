<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateCreative
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOfferCreative $creative, array $data): AffiliateOfferCreative
    {
        $offer = AffiliateOffer::query()->whereKey($creative->offer_id)->firstOrFail();
        $creative = $offer->creatives()->whereKey($creative->getKey())->firstOrFail();

        $rules = [
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];

        if ($creative->external_creative_id === null) {
            $rules += [
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::enum(CreativeType::class)],
                'description' => ['nullable', 'string'],
                'destination_url' => ['nullable', 'url:http,https', 'max:2048'],
                'width' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'height' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'html_code' => ['nullable', 'string'],
            ];
        }

        $creative->update(Validator::make($data, $rules)->validate());

        return $creative->refresh();
    }
}
