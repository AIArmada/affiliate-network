<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Strategies;

use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategyInterface;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Support\SiteContentFetcher;

final class MetaTagVerificationStrategy implements SiteVerificationStrategyInterface
{
    public function __construct(
        private readonly SiteContentFetcher $fetcher,
    ) {}

    public function methodKey(): string
    {
        return 'meta_tag';
    }

    public function label(): string
    {
        return 'HTML Meta Tag';
    }

    public function verify(AffiliateSite $site): bool
    {
        if ($site->verification_token === null) {
            return false;
        }

        $html = $this->fetcher->fetch($site->domain, '/');

        if ($html === null) {
            return false;
        }

        $token = preg_quote($site->verification_token, '/');

        return preg_match(
            '/<meta[^>]+name=["\']affiliate-network-verify["\'][^>]+content=["\']' . $token . '["\'][^>]*>/i',
            $html,
        ) === 1 || preg_match(
            '/<meta[^>]+content=["\']' . $token . '["\'][^>]+name=["\']affiliate-network-verify["\'][^>]*>/i',
            $html,
        ) === 1;
    }

    public function getInstructions(AffiliateSite $site): array
    {
        $token = $site->verification_token ?? '';

        return [
            'title' => 'HTML Meta Tag',
            'description' => 'Add this meta tag to the <head> section of your homepage.',
            'html' => "<meta name=\"affiliate-network-verify\" content=\"{$token}\">",
        ];
    }
}
