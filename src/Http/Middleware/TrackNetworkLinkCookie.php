<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Middleware;

use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracks network affiliate link codes in cookies for conversion attribution.
 *
 * When a visitor arrives via an affiliate network link redirect, this middleware
 * persists the link code in a cookie for later conversion attribution.
 */
final class TrackNetworkLinkCookie
{
    public function __construct(
        private readonly OfferLinkService $linkService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('affiliate-network.cookies.enabled', true)) {
            return $next($request);
        }

        if ($this->shouldRespectDoNotTrack($request)) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        $cookieName = config('affiliate-network.cookies.name', 'anl_session');
        $existingCookie = $request->cookie($cookieName);
        $linkCode = $this->resolveLinkCode($request);

        // If no new link code and no existing cookie, nothing to do
        if ($linkCode === null && $existingCookie === null) {
            return $response;
        }

        // New link code takes priority (allows attribution to most recent click)
        if ($linkCode !== null) {
            $link = $this->linkService->resolveLink($linkCode);

            if ($link !== null && ! $link->isExpired() && $link->offer->isActive()) {
                $cookieValue = $this->buildCookieValue($link->code, $link->affiliate_id, $link->offer_id);
                $this->setCookie($response, $cookieName, $cookieValue);
            }
        }

        return $response;
    }

    /**
     * Resolve link code from query parameters.
     */
    private function resolveLinkCode(Request $request): ?string
    {
        $keys = (array) config('affiliate-network.cookies.query_parameters', ['anl']);

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Build the cookie value with attribution data.
     *
     * @return string JSON-encoded attribution data
     */
    private function buildCookieValue(string $linkCode, string $affiliateId, string $offerId): string
    {
        return json_encode([
            'code' => $linkCode,
            'affiliate_id' => $affiliateId,
            'offer_id' => $offerId,
            'clicked_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Set the tracking cookie on the response.
     */
    private function setCookie(Response $response, string $name, string $value): void
    {
        $minutes = (int) config('affiliate-network.cookies.ttl_minutes', 60 * 24 * 30);

        $response->headers->setCookie(cookie(
            name: $name,
            value: $value,
            minutes: $minutes,
            path: config('affiliate-network.cookies.path', '/'),
            domain: config('affiliate-network.cookies.domain'),
            secure: config('affiliate-network.cookies.secure'),
            httpOnly: config('affiliate-network.cookies.http_only', true),
            raw: false,
            sameSite: config('affiliate-network.cookies.same_site', 'lax')
        ));
    }

    /**
     * Check if DNT header should be respected.
     */
    private function shouldRespectDoNotTrack(Request $request): bool
    {
        if (! config('affiliate-network.cookies.respect_dnt', false)) {
            return false;
        }

        return (string) $request->headers->get('DNT') === '1';
    }

    /**
     * Parse the cookie value to extract attribution data.
     *
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string}|null
     */
    public static function parseCookie(?string $cookieValue): ?array
    {
        if ($cookieValue === null || $cookieValue === '') {
            return null;
        }

        try {
            $data = json_decode($cookieValue, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data) || ! isset($data['code'], $data['affiliate_id'], $data['offer_id'])) {
                return null;
            }

            return $data;
        } catch (JsonException) {
            return null;
        }
    }
}
