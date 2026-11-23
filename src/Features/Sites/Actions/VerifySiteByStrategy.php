<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Actions;

use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategy;
use AIArmada\AffiliateNetwork\Data\SiteVerificationTarget;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Platform\ExtensionRegistry;
use RuntimeException;

final class VerifySiteByStrategy
{
    public function __construct(
        private readonly VerifySite $verifySite,
        private readonly GenerateVerificationToken $generateToken,
    ) {}

    public function execute(AffiliateSite $site, string $method): AffiliateSite
    {
        $strategy = $this->strategy($method);

        if (! $strategy instanceof SiteVerificationStrategy) {
            throw new RuntimeException("Unknown site verification strategy [{$method}].");
        }

        $token = $site->verification_token;

        if ($token === null || $token === '') {
            $token = $this->generateToken->execute($site);
            $site = $site->refresh();
        }

        $target = new SiteVerificationTarget(
            siteId: (string) $site->getKey(),
            domain: (string) $site->domain,
            verificationToken: $token,
        );

        if (! $strategy->verify($target)) {
            throw new RuntimeException("Site verification failed for strategy [{$method}].");
        }

        return $this->verifySite->execute($site, $strategy->methodKey());
    }

    /**
     * @return array<string, mixed>
     */
    public function getInstructions(AffiliateSite $site, string $method): array
    {
        $strategy = $this->strategy($method);

        if (! $strategy instanceof SiteVerificationStrategy) {
            throw new RuntimeException("Unknown site verification strategy [{$method}].");
        }

        $token = $site->verification_token;

        if ($token === null || $token === '') {
            $token = $this->generateToken->execute($site);
        }

        return $strategy->getInstructions(new SiteVerificationTarget(
            siteId: (string) $site->getKey(),
            domain: (string) $site->domain,
            verificationToken: $token,
        ));
    }

    /**
     * @return list<SiteVerificationStrategy>
     */
    public function strategies(): array
    {
        $registry = app(ExtensionRegistry::class);

        return $registry->resolve('site.verification_strategy', SiteVerificationStrategy::class);
    }

    public function hasStrategy(string $method): bool
    {
        return $this->strategy($method) instanceof SiteVerificationStrategy;
    }

    private function strategy(string $method): ?SiteVerificationStrategy
    {
        foreach ($this->strategies() as $strategy) {
            if ($strategy->methodKey() === $method) {
                return $strategy;
            }
        }

        return null;
    }
}
