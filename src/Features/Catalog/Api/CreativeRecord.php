<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Api;

use AIArmada\AffiliateNetwork\Data\CreativeType;

final readonly class CreativeRecord
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $offerId,
        public CreativeType $type,
        public string $name,
        public ?string $description,
        public ?string $externalCreativeId,
        public ?string $sourceAssetUrl,
        public ?string $destinationUrl,
        public ?int $width,
        public ?int $height,
        public ?string $htmlCode,
        public bool $isActive,
        public int $sortOrder,
        public ?array $metadata,
    ) {}
}
