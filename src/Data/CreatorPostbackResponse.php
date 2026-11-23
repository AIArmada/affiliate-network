<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class CreatorPostbackResponse
{
    public function __construct(
        public bool $successful,
        public ?int $httpStatus = null,
        public ?string $error = null,
    ) {}
}
