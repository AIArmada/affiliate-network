<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Support\Str;

final class GenerateVerificationToken
{
    public function execute(AffiliateSite $site): string
    {
        $token = 'affiliatenetwork-verify-' . Str::random(32);

        AffiliateSite::query()
            ->whereKey($site->getKey())
            ->update(['verification_token' => $token]);

        $site->forceFill(['verification_token' => $token]);

        return $token;
    }
}
