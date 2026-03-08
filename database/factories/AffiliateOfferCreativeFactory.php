<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Database\Factories;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
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
            'type' => AffiliateOfferCreative::TYPE_BANNER,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'url' => $this->faker->imageUrl(728, 90),
            'file_path' => null,
            'width' => 728,
            'height' => 90,
            'alt_text' => $this->faker->sentence(4),
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
            'type' => AffiliateOfferCreative::TYPE_BANNER,
            'width' => $width,
            'height' => $height,
            'url' => $this->faker->imageUrl($width, $height),
        ]);
    }

    /**
     * Text link creative.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AffiliateOfferCreative::TYPE_TEXT,
            'width' => null,
            'height' => null,
            'url' => null,
        ]);
    }

    /**
     * Email creative.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AffiliateOfferCreative::TYPE_EMAIL,
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
            'type' => AffiliateOfferCreative::TYPE_HTML,
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
            'type' => AffiliateOfferCreative::TYPE_VIDEO,
            'width' => 1920,
            'height' => 1080,
            'url' => $this->faker->url(),
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
