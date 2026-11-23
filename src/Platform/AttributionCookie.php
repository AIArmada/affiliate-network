<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use Throwable;

final class AttributionCookie
{
    /**
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string, sub1: string|null, sub2: string|null, sub3: string|null}|null
     */
    public static function parse(?string $cookieValue): ?array
    {
        if ($cookieValue === null || $cookieValue === '') {
            return null;
        }

        try {
            $decrypted = decrypt($cookieValue);

            if (! is_string($decrypted)) {
                return null;
            }

            $data = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)
                || ! is_string($data['code'] ?? null) || $data['code'] === ''
                || ! is_string($data['affiliate_id'] ?? null) || $data['affiliate_id'] === ''
                || ! is_string($data['offer_id'] ?? null) || $data['offer_id'] === ''
                || ! is_string($data['clicked_at'] ?? null) || $data['clicked_at'] === '') {
                return null;
            }

            return [
                'code' => $data['code'],
                'affiliate_id' => $data['affiliate_id'],
                'offer_id' => $data['offer_id'],
                'clicked_at' => $data['clicked_at'],
                'sub1' => is_string($data['sub1'] ?? null) ? $data['sub1'] : null,
                'sub2' => is_string($data['sub2'] ?? null) ? $data['sub2'] : null,
                'sub3' => is_string($data['sub3'] ?? null) ? $data['sub3'] : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function encode(array $values): string
    {
        return encrypt(json_encode($values, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function sign(array $values): array
    {
        $values['signature'] = self::signature($values);

        return $values;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function signature(array $snapshot): string
    {
        return hash_hmac('sha256', implode('|', [
            (string) $snapshot['link_code'],
            (string) $snapshot['affiliate_id'],
            (string) $snapshot['offer_id'],
            (string) $snapshot['clicked_at'],
            (string) ($snapshot['sub1'] ?? ''),
            (string) ($snapshot['sub2'] ?? ''),
            (string) ($snapshot['sub3'] ?? ''),
        ]), (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function verify(array $snapshot): bool
    {
        $signature = $snapshot['signature'] ?? null;

        return is_string($signature) && hash_equals(self::signature($snapshot), $signature);
    }
}
