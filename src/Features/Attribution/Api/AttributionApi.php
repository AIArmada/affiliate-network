<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Api;

use Carbon\CarbonImmutable;

interface AttributionApi
{
    public function findLinkByIdUnscoped(string $linkId): ?OfferLinkRecord;

    public function resolveLinkBySlug(string $slug): ?OfferLinkRecord;

    public function findLinkBySlugUnscoped(string $slug, bool $requireActive = false): ?OfferLinkRecord;

    public function resolveCouponForSite(string $siteId, string $code, ?CarbonImmutable $at = null): ?OfferCouponRecord;

    public function recordLinkCounters(string $linkId, int $revenueDeltaMinor, int $conversionDelta = 1): void;

    public function setLinkCounters(string $linkId, int $conversions, int $revenueMinor): void;

    /**
     * @return array{clicks: int, conversions: int, revenue: int, currency: string|null, conversion_rate: float, revenue_per_click: float}
     */
    public function linkStats(string $linkId): array;

    /**
     * @return list<string>
     */
    public function offerLinkIds(string $offerId): array;

    /**
     * @param  array<int, string>  $offerIds
     * @return array<string, array{clicks: int, conversions: int, revenue_minor: int}>
     */
    public function offerLinkTotals(array $offerIds): array;

    public function deleteForOffer(string $offerId): void;
}
