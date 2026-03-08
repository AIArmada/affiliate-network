<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateSite>
 */
class AffiliateSiteFactory extends Factory
{
    protected $model = AffiliateSite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = $this->faker->unique()->word() . '-' . $this->faker->randomNumber(4) . '.com';

        return [
            'name' => $this->faker->company() . ' Affiliate Network',
            'domain' => $domain,
            'description' => $this->faker->paragraph(),
            'status' => AffiliateSite::STATUS_PENDING,
            'verification_method' => null,
            'verification_token' => null,
            'verified_at' => null,
            'settings' => null,
            'metadata' => null,
        ];
    }

    /**
     * Site that is pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateSite::STATUS_PENDING,
            'verification_token' => 'affiliatenetwork-verify-' . Str::random(32),
        ]);
    }

    /**
     * Site that is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verification_method' => 'dns',
            'verification_token' => 'affiliatenetwork-verify-' . Str::random(32),
            'verified_at' => now(),
        ]);
    }

    /**
     * Site that is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateSite::STATUS_SUSPENDED,
            'verified_at' => now()->subDays(30),
        ]);
    }

    /**
     * Site that is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AffiliateSite::STATUS_REJECTED,
        ]);
    }

    /**
     * Site with an owner.
     */
    public function forOwner(object $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
    }

    /**
     * Site with custom settings.
     *
     * @param  array<string, mixed>  $settings
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => $settings,
        ]);
    }
}
