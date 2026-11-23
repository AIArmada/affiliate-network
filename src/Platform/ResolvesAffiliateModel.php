<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use Illuminate\Database\Eloquent\Model;

trait ResolvesAffiliateModel
{
    /**
     * @return class-string<Model>
     */
    public static function affiliateModel(): string
    {
        $model = config('affiliate-network.models.affiliate');

        if (! is_string($model) || ! class_exists($model)) {
            throw AffiliatesNotInstalled::forFeature('affiliate relations');
        }

        return $model;
    }
}
