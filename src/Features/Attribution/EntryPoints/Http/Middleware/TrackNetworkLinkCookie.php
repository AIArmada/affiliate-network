<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\EntryPoints\Http\Middleware;

use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Platform\AttributionCookie;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrackNetworkLinkCookie
{
    public function __construct(
        private readonly AttributionApi $attribution,
        private readonly CatalogApi $catalog,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('affiliate-network.cookies.enabled', true) || $this->shouldRespectDoNotTrack($request)) {
            return $next($request);
        }

        $cookieName = (string) config('affiliate-network.cookies.name', 'affiliate_network_link');
        $existingCookie = $request->cookie($cookieName);
        $existingCookie = is_string($existingCookie) ? $existingCookie : null;

        $incomingCode = $this->resolveLinkCode($request);

        if ($incomingCode === null) {
            return $this->handleCookieOnly($request, $next, $existingCookie);
        }

        $link = $this->attribution->findLinkBySlugUnscoped($incomingCode, true);

        if (! $link instanceof OfferLinkRecord || $link->isExpired
            || ! $this->catalog->wasOfferActiveAt($link->offerId, CarbonImmutable::now())) {
            return $next($request);
        }

        $subIds = $this->captureSubIds($request);
        $clickedAt = CarbonImmutable::now()->toIso8601String();
        $snapshot = $this->snapshot($link, $clickedAt, $subIds);
        $cookieValue = $this->buildCookieValue($link, $clickedAt, $subIds);

        $this->persistAttributionSnapshot($snapshot, true);

        $response = $next($request);
        $this->setCookie($response, $cookieName, $cookieValue);

        return $response;
    }

    private function handleCookieOnly(Request $request, Closure $next, ?string $existingCookie): Response
    {
        $payload = AttributionCookie::parse($existingCookie);

        if ($payload === null) {
            return $next($request);
        }

        $snapshot = $this->snapshotFromPayload($payload);

        if ($this->cartSnapshotMatches($snapshot)) {
            return $next($request);
        }

        $link = $this->attribution->findLinkBySlugUnscoped($payload['code'], true);

        if (! $link instanceof OfferLinkRecord
            || $link->isExpired
            || $link->affiliateId !== $payload['affiliate_id']
            || $link->offerId !== $payload['offer_id']
            || ! $this->catalog->wasOfferActiveAt($link->offerId, CarbonImmutable::now())) {
            return $next($request);
        }

        $this->persistAttributionSnapshot($snapshot, false);

        return $next($request);
    }

    private function resolveLinkCode(Request $request): ?string
    {
        $configuredKeys = (array) config('affiliate-network.cookies.query_parameters', ['anl']);
        $primaryKey = (string) config('affiliate-network.links.parameter', 'anl');
        $keys = array_values(array_unique([...$configuredKeys, $primaryKey]));

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{sub1: string|null, sub2: string|null, sub3: string|null}
     */
    private function captureSubIds(Request $request): array
    {
        $bounded = static function (mixed $value): ?string {
            if (! is_string($value) || $value === '') {
                return null;
            }

            return mb_substr($value, 0, 255);
        };

        return [
            'sub1' => $bounded($request->query('sub1')),
            'sub2' => $bounded($request->query('sub2')),
            'sub3' => $bounded($request->query('sub3')),
        ];
    }

    /**
     * @param  array{sub1: string|null, sub2: string|null, sub3: string|null}  $subIds
     */
    private function buildCookieValue(OfferLinkRecord $link, string $clickedAt, array $subIds): string
    {
        return AttributionCookie::encode([
            'code' => (string) $link->slug,
            'affiliate_id' => $link->affiliateId,
            'offer_id' => $link->offerId,
            'clicked_at' => $clickedAt,
            'sub1' => $subIds['sub1'],
            'sub2' => $subIds['sub2'],
            'sub3' => $subIds['sub3'],
        ]);
    }

    /**
     * @param  array{sub1: string|null, sub2: string|null, sub3: string|null}  $subIds
     * @return array<string, mixed>
     */
    private function snapshot(OfferLinkRecord $link, string $clickedAt, array $subIds): array
    {
        return AttributionCookie::sign([
            'link_code' => (string) $link->slug,
            'affiliate_id' => $link->affiliateId,
            'offer_id' => $link->offerId,
            'site_id' => $link->siteId,
            'clicked_at' => $clickedAt,
            'sub1' => $subIds['sub1'],
            'sub2' => $subIds['sub2'],
            'sub3' => $subIds['sub3'],
        ]);
    }

    /**
     * @param  array{code: string, affiliate_id: string, offer_id: string, clicked_at: string, sub1: string|null, sub2: string|null, sub3: string|null}  $payload
     * @return array<string, mixed>
     */
    private function snapshotFromPayload(array $payload): array
    {
        return AttributionCookie::sign([
            'link_code' => $payload['code'],
            'affiliate_id' => $payload['affiliate_id'],
            'offer_id' => $payload['offer_id'],
            'site_id' => null,
            'clicked_at' => $payload['clicked_at'],
            'sub1' => $payload['sub1'],
            'sub2' => $payload['sub2'],
            'sub3' => $payload['sub3'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function cartSnapshotMatches(array $snapshot): bool
    {
        $cart = $this->currentCart();

        if ($cart === null) {
            return false;
        }

        $stored = $cart->getMetadata('network_attribution');

        return is_array($stored)
            && ($stored['signature'] ?? null) === ($snapshot['signature'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $attribution
     */
    private function persistAttributionSnapshot(array $attribution, bool $isNewReferral): void
    {
        $cart = $this->currentCart();

        if ($cart === null) {
            return;
        }

        if (! $isNewReferral && $cart->getMetadata('network_attribution') === $attribution) {
            return;
        }

        $cart->setMetadata('network_attribution', $attribution);
    }

    private function currentCart(): ?object
    {
        $contract = 'AIArmada\\Cart\\Contracts\\CartManagerInterface';

        if (! interface_exists($contract) || ! app()->bound($contract)) {
            return null;
        }

        $manager = app($contract);

        return method_exists($manager, 'getCurrentCart') ? $manager->getCurrentCart() : null;
    }

    private function setCookie(Response $response, string $name, string $value): void
    {
        $minutes = (int) config('affiliate-network.cookies.ttl_minutes', 60 * 24 * 30);

        $response->headers->setCookie(cookie(
            name: $name,
            value: $value,
            minutes: $minutes,
            path: (string) config('affiliate-network.cookies.path', '/'),
            domain: config('affiliate-network.cookies.domain'),
            secure: (bool) config('affiliate-network.cookies.secure', true),
            httpOnly: (bool) config('affiliate-network.cookies.http_only', true),
            raw: false,
            sameSite: (string) config('affiliate-network.cookies.same_site', 'lax'),
        ));
    }

    private function shouldRespectDoNotTrack(Request $request): bool
    {
        return (bool) config('affiliate-network.cookies.respect_dnt', false)
            && (string) $request->headers->get('DNT') === '1';
    }
}
