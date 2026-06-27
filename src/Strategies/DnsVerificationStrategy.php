<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Strategies;

use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategyInterface;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Support\DnsRecordResolver;

final class DnsVerificationStrategy implements SiteVerificationStrategyInterface
{
    private readonly DnsRecordResolver $dns;

    public function __construct(?DnsRecordResolver $dns = null)
    {
        $this->dns = $dns ?? new DnsRecordResolver;
    }

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

        foreach ($this->dns->getRecords($site->domain, DNS_TXT) as $record) {
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
