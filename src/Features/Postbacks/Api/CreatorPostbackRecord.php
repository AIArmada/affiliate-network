<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Api;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use Carbon\CarbonImmutable;

final readonly class CreatorPostbackRecord
{
    /**
     * @param  list<PostbackEventType>  $events
     */
    public function __construct(
        public string $id,
        public string $affiliateId,
        public ?string $offerId,
        public string $urlTemplate,
        public string $httpMethod,
        public array $events,
        public bool $hasSigningSecret,
        public bool $isActive,
        public ?CarbonImmutable $createdAt = null,
    ) {}

    public function subscribesTo(PostbackEventType $eventType): bool
    {
        return in_array($eventType, $this->events, true);
    }
}
