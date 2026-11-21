<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Actions\Concerns\ValidatesCreativeInput;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use Illuminate\Support\Facades\Validator;

final class UpdateCreative
{
    use ValidatesCreativeInput;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOfferCreative $creative, array $data): AffiliateOfferCreative
    {
        $offer = AffiliateOffer::query()->whereKey($creative->offer_id)->firstOrFail();
        $creative = $offer->creatives()->whereKey($creative->getKey())->firstOrFail();

        $rules = $creative->external_creative_id === null
            ? self::manualCreativeRules() + self::operatorCreativeRules()
            : self::operatorCreativeRules();

        $creative->update(Validator::make($data, $rules)->validate());

        return $creative->refresh();
    }
}
