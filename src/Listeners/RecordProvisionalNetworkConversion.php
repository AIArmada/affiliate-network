<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
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
 * finalizer): resolves the network touch from the cookie, stamps
 * `network_touch_at` on the order, and posts a provisional leg. The
 * finalizer confirms or supersedes it once the engine has spoken.
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

        if (is_array($orderMetadata) && isset($orderMetadata['network_attribution'])) {
            return;
        }

        $attribution = $this->getAttributionFromCookie();

        if ($attribution === null) {
            $this->writeTouch($order, null);

            return;
        }

        $link = $this->linkService->resolveLink($attribution['code']);

        if ($link === null || $link->isExpired() || ! $link->offer->isActive()) {
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
        $leg = $this->linkService->recordConversion(
            $link,
            $revenueMinor,
            $order->currency ?? null,
            $order->order_number ?? (string) $order->getKey(),
            LegStatus::Provisional,
        );

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

        return TrackNetworkLinkCookie::parseCookie($cookieValue);
    }

    /**
     * @param  array{code: string, affiliate_id: string, offer_id: string, clicked_at: string}  $attribution
     */
    private function withinWindow(array $attribution): bool
    {
        $attributionWindow = config('affiliate-network.checkout.attribution_window_hours', 720);
        $clickedAt = $attribution['clicked_at'] ?? null;

        if (! is_string($clickedAt) || $clickedAt === '' || $attributionWindow <= 0) {
            return true;
        }

        try {
            return ! CarbonImmutable::parse($clickedAt)->addHours($attributionWindow)->isPast();
        } catch (Throwable) {
            // Malformed clicked_at from cookie — treat as expired to be safe.
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
