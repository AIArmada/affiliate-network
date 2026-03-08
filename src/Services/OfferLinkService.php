<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\URL;

final class OfferLinkService
{
    /**
     * Create a deep link for an affiliate to promote an offer.
     *
     * @param  array<string, mixed>  $options
     */
    public function createLink(
        AffiliateOffer $offer,
        Affiliate $affiliate,
        array $options = []
    ): AffiliateOfferLink {
        $targetUrl = $options['target_url'] ?? $offer->landing_url ?? "https://{$offer->site->domain}/";

        return AffiliateOfferLink::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $affiliate->id,
            'site_id' => $offer->site_id,
            'target_url' => $targetUrl,
            'custom_parameters' => $options['custom_parameters'] ?? null,
            'sub_id' => $options['sub_id'] ?? null,
            'sub_id_2' => $options['sub_id_2'] ?? null,
            'sub_id_3' => $options['sub_id_3'] ?? null,
            'is_active' => $options['is_active'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Generate a tracking URL for a link.
     */
    public function generateTrackingUrl(AffiliateOfferLink $link): string
    {
        $param = config('affiliate-network.links.parameter', 'anl');
        $signingKey = config('affiliate-network.links.signing_key', config('app.key'));
        $ttl = config('affiliate-network.links.default_ttl_minutes', 60 * 24 * 30);

        $data = [
            'code' => $link->code,
            'offer' => $link->offer_id,
            'aff' => $link->affiliate_id,
        ];

        if ($link->sub_id) {
            $data['sub1'] = $link->sub_id;
        }
        if ($link->sub_id_2) {
            $data['sub2'] = $link->sub_id_2;
        }
        if ($link->sub_id_3) {
            $data['sub3'] = $link->sub_id_3;
        }

        $payload = base64_encode(json_encode($data, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $signingKey);

        return URL::temporarySignedRoute(
            'affiliate-network.redirect',
            now()->addMinutes($ttl),
            [
                'code' => $link->code,
                $param => $payload,
                'sig' => mb_substr($signature, 0, 16),
            ]
        );
    }

    /**
     * Build a direct link with tracking parameters.
     */
    public function buildDirectLink(AffiliateOfferLink $link): string
    {
        $url = $link->target_url;
        $separator = str_contains($url, '?') ? '&' : '?';

        $params = [
            config('affiliate-network.links.parameter', 'anl') => $link->code,
        ];

        if ($link->sub_id) {
            $params['sub1'] = $link->sub_id;
        }
        if ($link->sub_id_2) {
            $params['sub2'] = $link->sub_id_2;
        }
        if ($link->sub_id_3) {
            $params['sub3'] = $link->sub_id_3;
        }

        if ($link->custom_parameters) {
            $customParams = [];
            parse_str($link->custom_parameters, $customParams);
            $params = array_merge($params, $customParams);
        }

        return $url . $separator . http_build_query($params);
    }

    /**
     * Resolve a link by its code.
     */
    public function resolveLink(string $code): ?AffiliateOfferLink
    {
        return AffiliateOfferLink::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->with(['offer', 'affiliate', 'site'])
            ->first();
    }

    /**
     * Record a click on a link.
     */
    public function recordClick(AffiliateOfferLink $link): void
    {
        $link->incrementClicks();
    }

    /**
     * Record a conversion on a link.
     */
    public function recordConversion(AffiliateOfferLink $link, int $revenueMinor = 0): void
    {
        $link->recordConversion($revenueMinor);
    }

    /**
     * Get statistics for a link.
     *
     * @return array<string, mixed>
     */
    public function getStats(AffiliateOfferLink $link): array
    {
        $conversionRate = $link->clicks > 0
            ? round(($link->conversions / $link->clicks) * 100, 2)
            : 0.0;

        $revenuePerClick = $link->clicks > 0
            ? round($link->revenue / $link->clicks, 2)
            : 0.0;

        return [
            'clicks' => $link->clicks,
            'conversions' => $link->conversions,
            'revenue' => $link->revenue,
            'conversion_rate' => $conversionRate,
            'revenue_per_click' => $revenuePerClick,
        ];
    }
}
