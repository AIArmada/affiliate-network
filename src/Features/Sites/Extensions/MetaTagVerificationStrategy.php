<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Extensions;

use AIArmada\AffiliateNetwork\Contracts\SiteContentClient;
use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategy;
use AIArmada\AffiliateNetwork\Data\SiteVerificationTarget;

final class MetaTagVerificationStrategy implements SiteVerificationStrategy
{
    public function __construct(
        private readonly SiteContentClient $client,
    ) {}

    public function methodKey(): string
    {
        return 'meta_tag';
    }

    public function label(): string
    {
        return 'HTML Meta Tag';
    }

    public function verify(SiteVerificationTarget $target): bool
    {
        if ($target->verificationToken === null || $target->verificationToken === '') {
            return false;
        }

        $html = $this->client->fetch($target->domain, '/');

        if ($html === null) {
            return false;
        }

        $token = preg_quote($target->verificationToken, '/');

        return preg_match(
            '/<meta[^>]+name=["\']affiliate-network-verify["\'][^>]+content=["\']' . $token . '["\'][^>]*>/i',
            $html,
        ) === 1 || preg_match(
            '/<meta[^>]+content=["\']' . $token . '["\'][^>]+name=["\']affiliate-network-verify["\'][^>]*>/i',
            $html,
        ) === 1;
    }

    public function getInstructions(SiteVerificationTarget $target): array
    {
        $token = (string) ($target->verificationToken ?? '');

        return [
            'title' => 'HTML Meta Tag',
            'description' => 'Add this meta tag to the <head> section of your homepage.',
            'html' => '<meta name="affiliate-network-verify" content="' . $token . '">',
        ];
    }
}
