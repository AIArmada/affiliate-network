<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class IssueCatalogToken
{
    public function execute(AffiliateSite $site): string
    {
        $token = Str::random(48);

        $site->forceFill([
            'catalog_token_encrypted' => encrypt($token),
            'catalog_token_issued_at' => CarbonImmutable::now(),
        ])->save();

        return $token;
    }
}
