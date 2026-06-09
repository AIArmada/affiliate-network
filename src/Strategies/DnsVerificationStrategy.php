<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Strategies;

use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategyInterface;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

final class DnsVerificationStrategy implements SiteVerificationStrategyInterface
{
    public function methodKey(): string
    {
        return 'dns';
    }

    public function label(): string
    {
        return 'DNS TXT Record';
    }

    public function verify(AffiliateSite $site): bool
    {
        if ($site->verification_token === null) {
            return false;
        }

        $records = @dns_get_record($site->domain, DNS_TXT);

        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && $record['txt'] === $site->verification_token) {
                return true;
            }
        }

        return false;
    }

    public function getInstructions(AffiliateSite $site): array
    {
        $token = $site->verification_token ?? '';

        return [
            'title' => 'DNS TXT Record',
            'description' => "Add a TXT record to your domain's DNS settings.",
            'record_type' => 'TXT',
            'record_name' => '@',
            'record_value' => $token,
        ];
    }
}
