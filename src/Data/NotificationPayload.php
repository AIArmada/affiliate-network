<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

final readonly class NotificationPayload
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $context = [],
    ) {}
}
