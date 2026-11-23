<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Extensions;

use AIArmada\AffiliateNetwork\Contracts\SiteContentClient;
use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategy;
use AIArmada\AffiliateNetwork\Data\SiteVerificationTarget;

final class FileVerificationStrategy implements SiteVerificationStrategy
{
    public function __construct(
        private readonly SiteContentClient $client,
    ) {}

    public function methodKey(): string
    {
        return 'file';
    }

    public function label(): string
    {
        return 'Verification File';
    }

    public function verify(SiteVerificationTarget $target): bool
    {
        if ($target->verificationToken === null || $target->verificationToken === '') {
            return false;
        }

        $content = $this->client->fetch($target->domain, '/.well-known/affiliate-network-verify.txt');

        if ($content === null) {
            return false;
        }

        return hash_equals($target->verificationToken, mb_trim($content));
    }

    public function getInstructions(SiteVerificationTarget $target): array
    {
        return [
            'title' => 'Verification File',
            'description' => 'Create a file at the following path with the token as contents.',
            'path' => '/.well-known/affiliate-network-verify.txt',
            'content' => (string) ($target->verificationToken ?? ''),
        ];
    }
}
