<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Strategies;

use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategyInterface;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Support\SiteContentFetcher;

final class FileVerificationStrategy implements SiteVerificationStrategyInterface
{
    public function __construct(
        private readonly SiteContentFetcher $fetcher,
    ) {}

    public function methodKey(): string
    {
        return 'file';
    }

    public function label(): string
    {
        return 'Verification File';
    }

    public function verify(AffiliateSite $site): bool
    {
        if ($site->verification_token === null) {
            return false;
        }

        $content = $this->fetcher->fetch($site->domain, '/.well-known/affiliate-network-verify.txt');

        if ($content === null) {
            return false;
        }

        return mb_trim($content) === $site->verification_token;
    }

    public function getInstructions(AffiliateSite $site): array
    {
        $token = $site->verification_token ?? '';

        return [
            'title' => 'Verification File',
            'description' => 'Create a file at the following path with the token as contents.',
            'path' => '/.well-known/affiliate-network-verify.txt',
            'content' => $token,
        ];
    }
}
