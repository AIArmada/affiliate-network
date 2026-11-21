<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Actions\Concerns\ValidatesCreativeInput;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use Illuminate\Support\Facades\Validator;

final class CreateCreative
{
    use ValidatesCreativeInput;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOffer $offer, array $data): AffiliateOfferCreative
    {
        $offer = AffiliateOffer::query()->whereKey($offer->getKey())->firstOrFail();
        $validated = Validator::make(
            $data,
            self::manualCreativeRules() + self::operatorCreativeRules(),
        )->validate();

        return $offer->creatives()->create($validated + [
            'external_creative_id' => null,
            'source_asset_url' => null,
        ]);
    }
}
