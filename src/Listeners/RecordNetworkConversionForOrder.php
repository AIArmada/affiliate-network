<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Records network affiliate conversions when orders are completed.
 *
 * Listens for the CommissionAttributionRequired event and checks if the order
 * originated from a network affiliate link (via cookie tracking).
 */
final class RecordNetworkConversionForOrder
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

        // Get attribution data from cookie
        $attribution = $this->getAttributionFromCookie();

        if ($attribution === null) {
            return;
        }

        // Resolve the link to ensure it's still valid
        $link = $this->linkService->resolveLink($attribution['code']);

        if ($link === null) {
            return;
        }

        // Verify the link is still active and offer is valid
        if ($link->isExpired() || ! $link->offer->isActive()) {
            return;
        }

        // Check attribution window (optional)
        $attributionWindow = config('affiliate-network.checkout.attribution_window_hours', 720); // 30 days
        $clickedAt = $attribution['clicked_at'] ?? null;

        if (is_string($clickedAt) && $clickedAt !== '' && $attributionWindow > 0) {
            try {
                $clickTime = CarbonImmutable::parse($clickedAt);

                if ($clickTime->addHours($attributionWindow)->isPast()) {
                    return; // Click is outside attribution window
                }
            } catch (Throwable) {
                // Malformed clicked_at from cookie — treat as expired to be safe.
                return;
            }
        }

        // Record the conversion
        $revenueMinor = $order->grand_total ?? 0;
        $this->linkService->recordConversion($link, $revenueMinor);

        // Store attribution data in order metadata for tracking
        $this->storeAttributionInOrder($order, $link, $attribution);
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
     * Store network attribution data in the order for tracking/reporting.
     *
     * @param  array<string, mixed>  $attribution
     */
    private function storeAttributionInOrder(Order $order, AffiliateOfferLink $link, array $attribution): void
    {
        $metadata = $order->metadata ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['network_attribution'] = [
            'link_code' => $link->code,
            'link_id' => $link->id,
            'affiliate_id' => $link->affiliate_id,
            'offer_id' => $link->offer_id,
            'site_id' => $link->site_id,
            'clicked_at' => $attribution['clicked_at'] ?? null,
            'converted_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $order->update(['metadata' => $metadata]);
    }
}
