<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

final class QueryParameters
{
    /**
     * Parse a query string into string parameters.
     *
     * @return array<string, string>
     */
    public static function parse(mixed $query): array
    {
        if (! is_string($query) || $query === '') {
            return [];
        }

        $params = [];
        parse_str($query, $params);

        return array_filter(
            is_array($params) ? $params : [],
            fn (mixed $value): bool => is_string($value) && $value !== '',
        );
    }
}
