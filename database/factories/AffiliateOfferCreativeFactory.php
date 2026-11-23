<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateOfferCreative>
 */
class AffiliateOfferCreativeFactory extends Factory
{
    protected $model = AffiliateOfferCreative::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => AffiliateOfferFactory::new(),
            'type' => CreativeType::Banner,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'external_creative_id' => null,
            'source_asset_url' => null,
            'destination_url' => null,
            'width' => 728,
            'height' => 90,
            'html_code' => null,
            'is_active' => true,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }

    /**
     * Banner creative.
     */
    public function banner(int $width = 728, int $height = 90): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Banner,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Text link creative.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Text,
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * Email creative.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Email,
            'width' => null,
            'height' => null,
            'html_code' => '<html><body><h1>' . $this->faker->sentence() . '</h1></body></html>',
        ]);
    }

    /**
     * HTML embed creative.
     */
    public function html(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Html,
            'width' => null,
            'height' => null,
            'html_code' => '<div class="affiliate-widget">' . $this->faker->paragraph() . '</div>',
        ]);
    }

    /**
     * Video creative.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Video,
            'width' => 1920,
            'height' => 1080,
        ]);
    }

    /**
     * Image creative.
     */
    public function image(int $width = 1200, int $height = 628): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Image,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Document creative.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreativeType::Document,
            'width' => null,
            'height' => null,
        ]);
    }

    public function withDestination(string $destinationUrl): static
    {
        return $this->state(fn (array $attributes) => [
            'destination_url' => $destinationUrl,
        ]);
    }

    public function withAsset(string $assetUrl): static
    {
        return $this->state(fn (array $attributes) => [
            'source_asset_url' => $assetUrl,
        ]);
    }

    /**
     * Creative that is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Creative that is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Creative for a specific offer.
     */
    public function forOffer(AffiliateOffer $offer): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_id' => $offer->id,
        ]);
    }

    /**
     * Creative with specific sort order.
     */
    public function sortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
