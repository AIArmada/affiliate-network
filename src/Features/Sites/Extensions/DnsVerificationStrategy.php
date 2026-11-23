<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Extensions;

use AIArmada\AffiliateNetwork\Contracts\DnsResolver;
use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategy;
use AIArmada\AffiliateNetwork\Data\SiteVerificationTarget;

final class DnsVerificationStrategy implements SiteVerificationStrategy
{
    public function __construct(
        private readonly DnsResolver $dns,
    ) {}

    public function methodKey(): string
    {
        return 'dns';
    }

    public function label(): string
    {
        return 'DNS TXT Record';
    }

    public function verify(SiteVerificationTarget $target): bool
    {
        if ($target->verificationToken === null || $target->verificationToken === '') {
            return false;
        }

        foreach ($this->dns->getTxtRecords($target->domain) as $record) {
            $txt = $record['txt'] ?? null;

            if (is_string($txt) && hash_equals($target->verificationToken, $txt)) {
                return true;
            }
        }

        return false;
    }

    public function getInstructions(SiteVerificationTarget $target): array
    {
        return [
            'title' => 'DNS TXT Record',
            'description' => "Add a TXT record to your domain's DNS settings.",
            'record_type' => 'TXT',
            'record_name' => '@',
            'record_value' => (string) ($target->verificationToken ?? ''),
        ];
    }
}
