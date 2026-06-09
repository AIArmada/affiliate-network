<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Contracts\SiteVerificationStrategyInterface;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Str;

final class SiteVerificationService
{
    private array $strategies = [];

    public function __construct(
        #[Tag('affiliate-network.site_verification_strategy')]
        iterable $strategies = [],
    ) {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->methodKey()] = $strategy;
        }
    }

    public function strategies(): array
    {
        return array_values($this->strategies);
    }

    public function hasStrategy(string $method): bool
    {
        return isset($this->strategies[$method]);
    }

    public function resolveStrategy(string $method): ?SiteVerificationStrategyInterface
    {
        return $this->strategies[$method] ?? null;
    }

    public function generateToken(AffiliateSite $site): string
    {
        $token = 'affiliatenetwork-verify-' . Str::random(32);

        $site->update([
            'verification_token' => $token,
        ]);

        return $token;
    }

    public function verify(AffiliateSite $site, string $method): bool
    {
        $strategy = $this->resolveStrategy($method);

        if ($strategy === null) {
            return false;
        }

        if ($site->verification_token === null) {
            return false;
        }

        $verified = $strategy->verify($site);

        if ($verified) {
            $site->update([
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verification_method' => $method,
                'verified_at' => CarbonImmutable::now(),
            ]);
        }

        return $verified;
    }

    public function getInstructions(AffiliateSite $site, string $method): array
    {
        $strategy = $this->resolveStrategy($method);

        if ($strategy !== null) {
            if ($site->verification_token === null) {
                $this->generateToken($site);
            }

            return $strategy->getInstructions($site);
        }

        return [];
    }
}
