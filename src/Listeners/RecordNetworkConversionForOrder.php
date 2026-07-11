<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Listeners;

use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
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

        $orderOwnerType = property_exists($event, 'owner_type') ? $event->owner_type : $order->owner_type;
        $orderOwnerId = property_exists($event, 'owner_id') ? $event->owner_id : $order->owner_id;

        try {
            $ownerTuple = OwnerTupleParser::fromTypeAndId($orderOwnerType, $orderOwnerId);
        } catch (InvalidArgumentException) {
            Log::warning('malformed_owner_tuple', [
                'component' => 'affiliate-network',
                'owner_type' => $orderOwnerType,
                'owner_id' => $orderOwnerId,
            ]);

            return;
        }

        // Get attribution data from cookie, falling back to persisted metadata
        $attribution = $this->getAttributionFromCookie();

        if ($attribution === null) {
            $attribution = $this->getAttributionFromOrderMetadata($order);
        }

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

        // Validate owner: link's site owner must match order owner
        $site = AffiliateSite::withoutGlobalScopes()->whereKey($link->site_id)->first();
        $linkSiteOwnerType = $site?->owner_type ?? null;
        $linkSiteOwnerId = $site?->owner_id ?? null;

        $orderHasOwner = $ownerTuple->isOwner();
        $linkHasOwner = $linkSiteOwnerType !== null && $linkSiteOwnerId !== null;

        if (! $orderHasOwner && $linkHasOwner) {
            Log::warning('owner_mismatch', [
                'component' => 'affiliate-network',
                'reason' => 'Order is global but link belongs to an owner.',
            ]);

            return;
        }

        if ($orderHasOwner && ! $linkHasOwner) {
            Log::warning('owner_mismatch', [
                'component' => 'affiliate-network',
                'reason' => 'Order belongs to an owner but link site is global or missing.',
            ]);

            return;
        }

        if ($orderHasOwner && $linkHasOwner) {
            if ($ownerTuple->owner_type !== $linkSiteOwnerType || $ownerTuple->owner_id !== $linkSiteOwnerId) {
                Log::warning('owner_mismatch', [
                    'component' => 'affiliate-network',
                    'reason' => 'Order owner does not match link site owner.',
                ]);

                return;
            }

            $ownerModel = $ownerTuple->toOwnerModel();

            if ($ownerModel !== null) {
                OwnerContext::withOwner($ownerModel, function () use ($link, $order, $attribution): void {
                    $this->recordAndStore($link, $order, $attribution);
                });

                return;
            }
        }

        // Both global or owner match confirmed for null-owner case
        OwnerContext::withOwner(null, function () use ($link, $order, $attribution): void {
            $this->recordAndStore($link, $order, $attribution);
        });
    }

    private function recordAndStore(AffiliateOfferLink $link, Order $order, array $attribution): void
    {
        $revenueMinor = $order->grand_total ?? 0;
        $currency = $order->currency ?? 'USD';

        $this->linkService->recordConversion($link, $revenueMinor, (string) $order->getKey(), $currency);

        $this->storeAttributionInOrder($order, $link, $attribution);
    }

    /**
     * Get attribution data from order metadata (persisted at checkout time).
     *
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string}|null
     */
    private function getAttributionFromOrderMetadata(Order $order): ?array
    {
        $code = $order->metadata['affiliate_link_code'] ?? null;

        if (! is_string($code)) {
            return null;
        }

        return [
            'code' => $code,
            'affiliate_id' => '',
            'offer_id' => '',
            'clicked_at' => '',
        ];
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

        $metadata['affiliate_link_code'] = $link->code;
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
