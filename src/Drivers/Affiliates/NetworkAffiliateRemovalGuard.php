<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Drivers\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\AffiliateReferenceReader;
use AIArmada\Affiliates\Contracts\AffiliateRemovalGuard;
use Illuminate\Validation\ValidationException;

final readonly class NetworkAffiliateRemovalGuard implements AffiliateRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionInstanceId): void
    {
        foreach (app()->tagged(AffiliateReferenceReader::class) as $reader) {
            if ($reader->existsFor($affiliateId, $connectionInstanceId)) {
                throw ValidationException::withMessages(['affiliate' => 'An affiliate with network records cannot be deleted.']);
            }
        }
    }
}
