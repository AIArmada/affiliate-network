<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Api;

final readonly class CategoryRecord
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $icon,
        public int $sortOrder,
        public bool $isActive,
    ) {}
}
