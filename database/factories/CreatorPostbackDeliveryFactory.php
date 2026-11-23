<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreatorPostbackDelivery>
 */
class CreatorPostbackDeliveryFactory extends Factory
{
    protected $model = CreatorPostbackDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'endpoint_id' => CreatorPostbackEndpointFactory::new(),
            'leg_id' => NetworkConversionLegFactory::new(),
            'event_type' => PostbackEventType::ConversionRecorded->value,
            'resolved_url' => 'https://example.com/postbacks?event=conversion.recorded',
            'status' => PostbackDeliveryStatus::Pending,
            'http_status' => null,
            'attempts' => 0,
            'last_error' => null,
            'delivered_at' => null,
        ];
    }

    public function forEndpoint(CreatorPostbackEndpoint $endpoint): static
    {
        return $this->state(fn (array $attributes) => [
            'endpoint_id' => $endpoint->getKey(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostbackDeliveryStatus::Delivered,
            'http_status' => 200,
            'attempts' => 1,
            'last_error' => null,
            'delivered_at' => CarbonImmutable::now(),
        ]);
    }

    public function failed(string $error = 'Connection refused'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostbackDeliveryStatus::Failed,
            'http_status' => 500,
            'attempts' => 1,
            'last_error' => $error,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostbackDeliveryStatus::Pending,
            'http_status' => null,
            'attempts' => 0,
            'last_error' => null,
            'delivered_at' => null,
        ]);
    }
}
