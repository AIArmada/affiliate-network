<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Queries;

use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;

final class EvaluateOfferRestrictions
{
    public function evaluate(OfferRecord $offer, RestrictionContext $context): ?string
    {
        $restrictions = $offer->restrictions ?? [];

        if ($context->country !== null && $this->violates(
            $context->country,
            $restrictions['allowed_countries'] ?? null,
            $restrictions['blocked_countries'] ?? null,
        )) {
            return 'country_restricted';
        }

        if ($context->channel !== null && $this->violates(
            $context->channel,
            $restrictions['allowed_channels'] ?? null,
            $restrictions['blocked_channels'] ?? null,
        )) {
            return 'channel_restricted';
        }

        if ($context->device !== null && $this->violates(
            $context->device,
            $restrictions['allowed_devices'] ?? null,
            $restrictions['blocked_devices'] ?? null,
        )) {
            return 'device_restricted';
        }

        if ($context->orderMinor !== null) {
            $minimum = $restrictions['min_order_minor'] ?? null;

            if (is_numeric($minimum) && $context->orderMinor < (int) $minimum) {
                return 'order_below_minimum';
            }

            $maximum = $restrictions['max_order_minor'] ?? null;

            if (is_numeric($maximum) && $context->orderMinor > (int) $maximum) {
                return 'order_above_maximum';
            }
        }

        return null;
    }

    private function violates(string $value, mixed $allowed, mixed $blocked): bool
    {
        $normalized = mb_strtolower(mb_trim($value));

        if ($this->contains($blocked, $normalized)) {
            return true;
        }

        $allowedList = $this->normalizeList($allowed);

        return $allowedList !== [] && ! in_array($normalized, $allowedList, true);
    }

    private function contains(mixed $values, string $needle): bool
    {
        return in_array($needle, $this->normalizeList($values), true);
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value) && mb_trim($value) !== '') {
                $normalized[] = mb_strtolower(mb_trim($value));
            }
        }

        return $normalized;
    }
}
