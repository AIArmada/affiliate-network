<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreatorPostbackEndpoint>
 */
class CreatorPostbackEndpointFactory extends Factory
{
    protected $model = CreatorPostbackEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_id' => fn () => (string) Str::uuid(),
            'offer_id' => null,
            'url_template' => 'https://example.com/postbacks?event={event}&leg={leg_id}',
            'http_method' => 'POST',
            'events' => [PostbackEventType::ConversionRecorded->value],
            'signing_secret_encrypted' => null,
            'is_active' => true,
        ];
    }

    public function forAffiliateId(string $affiliateId): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_id' => $affiliateId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSecret(string $encryptedSecret): static
    {
        return $this->state(fn (array $attributes) => [
            'signing_secret_encrypted' => $encryptedSecret,
        ]);
    }

    /**
     * @param  array<int, PostbackEventType>  $events
     */
    public function forEvents(array $events): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => array_map(
                static fn (PostbackEventType $event): string => $event->value,
                $events,
            ),
        ]);
    }
}
