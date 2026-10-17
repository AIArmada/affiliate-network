<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Exceptions\AffiliateProfileRequired;
use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Records a provisional network conversion when orders complete.
 *
 * First of the ordered attribution listeners (provisional, engine,
 * finalizer): resolves the durable order snapshot before falling back
 * to the request cookie, stamps `network_touch_at` on the order, and
 * posts a provisional leg. The finalizer confirms or supersedes it once
 * the engine has spoken.
 */
final class RecordProvisionalNetworkConversion
{
    public function __construct(
        private readonly OfferLinkService $linkService,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        if (! config('affiliate-network.checkout.enabled', false)) {
            return;
        }

        $order = $event->order;

        // Idempotency: a redelivered event must not double-count the conversion.
        $orderMetadata = $order->metadata;

        $storedAttribution = is_array($orderMetadata) ? ($orderMetadata['network_attribution'] ?? null) : null;

        if (is_array($storedAttribution) && is_string($storedAttribution['leg_id'] ?? null)) {
            return;
        }

        $attribution = $this->getAttributionFromOrder($order)
            ?? $this->getAttributionFromCookie();

        if ($attribution === null) {
            $this->writeTouch($order, null);

            return;
        }

        $link = $this->linkService->resolveLink($attribution['code']);

        if (
            $link === null
            || $link->isExpired()
            || ! $link->offer->isActive()
            || (string) $link->affiliate_id !== $attribution['affiliate_id']
            || (string) $link->offer_id !== $attribution['offer_id']
        ) {
            $this->writeTouch($order, null);

            return;
        }

        if (! $this->withinWindow($attribution)) {
            $this->writeTouch($order, null);

            return;
        }

        $clickedAt = $attribution['clicked_at'] ?? null;
        $this->writeTouch($order, is_string($clickedAt) ? $clickedAt : null);

        $revenueMinor = $order->grand_total ?? 0;

        try {
            $leg = $this->linkService->recordConversion(
                $link,
                $revenueMinor,
                $order->currency ?? null,
                $order->order_number ?? (string) $order->getKey(),
                LegStatus::Provisional,
            );
        } catch (AffiliateProfileRequired) {
            $this->writeTouch($order, null);

            return;
        }

        $this->storeAttributionInOrder($order, $link, $attribution, $leg?->getKey());
    }

    /**
     * Get attribution data from the tracking cookie.
     *
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string}|null
     */
    private function getAttributionFromCookie(): ?array
    {
        $cookieName = config('affiliate-network.cookies.name', 'affiliate_network_link');
        $cookieValue = request()->cookie($cookieName);

        if (! is_string($cookieValue)) {
            return null;
        }

        $attribution = TrackNetworkLinkCookie::parseCookie($cookieValue);

        if (
            ! is_array($attribution)
            || ! is_string($attribution['code'] ?? null) || $attribution['code'] === ''
            || ! is_string($attribution['affiliate_id'] ?? null) || $attribution['affiliate_id'] === ''
            || ! is_string($attribution['offer_id'] ?? null) || $attribution['offer_id'] === ''
            || ! is_string($attribution['clicked_at'] ?? null) || $attribution['clicked_at'] === ''
        ) {
            return null;
        }

        return [
            'code' => $attribution['code'],
            'affiliate_id' => $attribution['affiliate_id'],
            'offer_id' => $attribution['offer_id'],
            'clicked_at' => $attribution['clicked_at'],
        ];
    }

    /**
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string}|null
     */
    private function getAttributionFromOrder(Order $order): ?array
    {
        $attribution = data_get($order->metadata, 'network_attribution');

        if (! is_array($attribution)) {
            return null;
        }

        $linkCode = $attribution['link_code'] ?? null;
        $affiliateId = $attribution['affiliate_id'] ?? null;
        $offerId = $attribution['offer_id'] ?? null;
        $clickedAt = $attribution['clicked_at'] ?? null;
        $signature = $attribution['signature'] ?? null;

        if (
            ! is_string($linkCode) || $linkCode === ''
            || ! is_string($affiliateId) || $affiliateId === ''
            || ! is_string($offerId) || $offerId === ''
            || ! is_string($clickedAt) || $clickedAt === ''
            || ! is_string($signature) || $signature === ''
        ) {
            return null;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            implode('|', [$linkCode, $affiliateId, $offerId, $clickedAt]),
            (string) config('app.key'),
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        return [
            'code' => $linkCode,
            'affiliate_id' => $affiliateId,
            'offer_id' => $offerId,
            'clicked_at' => $clickedAt,
        ];
    }

    /**
     * @param  array{code: string, affiliate_id: string, offer_id: string, clicked_at: string}  $attribution
     */
    private function withinWindow(array $attribution): bool
    {
        $attributionWindow = config('affiliate-network.checkout.attribution_window_hours', 720);
        $clickedAt = $attribution['clicked_at'] ?? null;

        try {
            if (! is_string($clickedAt) || $clickedAt === '') {
                return false;
            }

            $clickedAt = CarbonImmutable::parse($clickedAt);
            $now = CarbonImmutable::now();

            if ($clickedAt->greaterThan($now->addMinutes(5))) {
                return false;
            }

            if ($attributionWindow <= 0) {
                return true;
            }

            return ! $clickedAt->addHours($attributionWindow)->isPast();
        } catch (Throwable) {
            // Malformed clicked_at — treat it as out of window.
            return false;
        }
    }

    private function writeTouch(Order $order, ?string $clickedAt): void
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $metadata['network_touch_at'] = $clickedAt;

        $order->update(['metadata' => $metadata]);
    }

    /**
     * Store network attribution data in the order for tracking/reporting.
     *
     * @param  array<string, mixed>  $attribution
     */
    private function storeAttributionInOrder(Order $order, AffiliateOfferLink $link, array $attribution, mixed $legId): void
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        $metadata['network_attribution'] = [
            'link_code' => $link->trackedSlug(),
            'link_id' => $link->id,
            'leg_id' => $legId !== null ? (string) $legId : null,
            'affiliate_id' => $link->affiliate_id,
            'offer_id' => $link->offer_id,
            'site_id' => $link->site_id,
            'clicked_at' => $attribution['clicked_at'] ?? null,
            'converted_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $order->update(['metadata' => $metadata]);
    }
}
