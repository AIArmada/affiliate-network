<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Api;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use Carbon\CarbonImmutable;

final readonly class OfferRecord
{
    /**
     * @param  array<int, array<string, mixed>>|null  $volumeTiers
     * @param  array<int, array<string, mixed>>|null  $activePromotions
     * @param  array<string, mixed>|null  $eventRates
     * @param  array<string, mixed>|null  $restrictions
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $siteId,
        public ?string $categoryId,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $terms,
        public OfferStatus $status,
        public OfferVisibility $visibility,
        public OfferSource $source,
        public ?int $rateBaseBp,
        public ?int $rateFixedMinor,
        public ?string $currency,
        public ?int $cookieDays,
        public ?array $volumeTiers,
        public ?array $activePromotions,
        public bool $isFeatured,
        public bool $requiresApproval,
        public ?string $landingUrl,
        public ?string $fallbackUrl,
        public ?string $fallbackOfferId,
        public bool $allowDeepLinking,
        public ?array $eventRates,
        public ?array $restrictions,
        public ?array $metadata,
        public ?int $networkFeeBp,
        public int $lockingDays,
        public ?int $dailyConversionCap,
        public ?int $monthlyConversionCap,
        public ?int $totalConversionCap,
        public ?int $perAffiliateConversionCap,
        public ?int $monthlyPayoutCapMinor,
        public ?int $totalPayoutCapMinor,
        public ?string $externalProgramId,
        public ?string $subjectType,
        public ?string $subjectKey,
        public ?string $sourceUrl,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?CarbonImmutable $publishedAt,
        public ?CarbonImmutable $archivedAt,
    ) {}

    public function isActiveAt(CarbonImmutable $at): bool
    {
        return $this->wasActiveAt($at);
    }

    public function wasActiveAt(CarbonImmutable $at): bool
    {
        if ($this->status === OfferStatus::Draft) {
            return false;
        }

        if ($this->publishedAt === null) {
            return false;
        }

        if ($at->lt($this->publishedAt)) {
            return false;
        }

        if ($this->archivedAt !== null && $at->gt($this->archivedAt)) {
            return false;
        }

        if ($this->startsAt !== null && $at->lt($this->startsAt)) {
            return false;
        }

        return ! ($this->endsAt !== null && $at->gt($this->endsAt));
    }
}
