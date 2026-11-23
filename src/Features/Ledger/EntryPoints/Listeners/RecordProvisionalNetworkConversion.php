<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Exceptions\AffiliateProfileRequired;
use AIArmada\AffiliateNetwork\Exceptions\CapExceededException;
use AIArmada\AffiliateNetwork\Exceptions\RestrictionViolatedException;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordOrderAttribution;
use AIArmada\AffiliateNetwork\Platform\AttributionCookie;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

final class RecordProvisionalNetworkConversion
{
    public function __construct(
        private readonly AttributionApi $attribution,
        private readonly CatalogApi $catalog,
        private readonly RecordNetworkConversion $recordConversion,
        private readonly RecordOrderAttribution $orderAttribution,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        if (! config('affiliate-network.checkout.enabled', false)) {
            return;
        }

        $order = $event->order;
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        $stored = $metadata['network_attribution'] ?? null;

        if (is_array($stored) && is_string($stored['leg_id'] ?? null) && $stored['leg_id'] !== '') {
            return;
        }

        $attribution = $this->attributionFromOrder($metadata) ?? $this->attributionFromCookie();

        if ($attribution === null) {
            $this->orderAttribution->writeTouch($order, null);

            return;
        }

        $link = $this->attribution->findLinkBySlugUnscoped($attribution['code']);

        if (! $link instanceof OfferLinkRecord
            || $link->isExpired
            || $link->affiliateId !== $attribution['affiliate_id']
            || $link->offerId !== $attribution['offer_id']
            || ! $this->catalog->wasOfferActiveAt($link->offerId, CarbonImmutable::parse($attribution['clicked_at']))) {
            $this->orderAttribution->writeTouch($order, null);

            return;
        }

        if (! $this->withinWindow($attribution['clicked_at'])) {
            $this->orderAttribution->writeTouch($order, null);

            return;
        }

        $this->orderAttribution->writeTouch($order, $attribution['clicked_at']);

        try {
            $leg = $this->recordConversion->execute(
                offerId: $link->offerId,
                affiliateId: $link->affiliateId,
                revenueMinor: (int) ($order->grand_total ?? 0),
                currency: is_string($order->currency ?? null) ? $order->currency : null,
                externalReference: (string) ($order->order_number ?? $order->getKey()),
                eventType: 'purchase',
                status: LegStatus::Provisional,
                linkId: $link->id,
                linkCode: $link->slug,
                subId: $attribution['sub1'],
                subId2: $attribution['sub2'],
                subId3: $attribution['sub3'],
            );
        } catch (AffiliateProfileRequired | CapExceededException | RestrictionViolatedException) {
            $this->orderAttribution->writeTouch($order, null);

            return;
        }

        $this->orderAttribution->writeAttribution($order, [
            'link_code' => $link->slug,
            'link_id' => $link->id,
            'leg_id' => (string) $leg->getKey(),
            'affiliate_id' => $link->affiliateId,
            'offer_id' => $link->offerId,
            'site_id' => $link->siteId,
            'clicked_at' => $attribution['clicked_at'],
            'sub1' => $attribution['sub1'] ?? $link->subId,
            'sub2' => $attribution['sub2'] ?? $link->subId2,
            'sub3' => $attribution['sub3'] ?? $link->subId3,
        ]);
    }

    /**
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string, sub1: string|null, sub2: string|null, sub3: string|null}|null
     */
    private function attributionFromCookie(): ?array
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request instanceof Request) {
            return null;
        }

        $cookieName = (string) config('affiliate-network.cookies.name', 'affiliate_network_link');
        $cookieValue = $request->cookie($cookieName);

        return AttributionCookie::parse(is_string($cookieValue) ? $cookieValue : null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{code: string, affiliate_id: string, offer_id: string, clicked_at: string, sub1: string|null, sub2: string|null, sub3: string|null}|null
     */
    private function attributionFromOrder(array $metadata): ?array
    {
        $attribution = $metadata['network_attribution'] ?? null;

        if (! is_array($attribution)) {
            return null;
        }

        $linkCode = $attribution['link_code'] ?? null;
        $affiliateId = $attribution['affiliate_id'] ?? null;
        $offerId = $attribution['offer_id'] ?? null;
        $clickedAt = $attribution['clicked_at'] ?? null;
        $signature = $attribution['signature'] ?? null;

        if (! is_string($linkCode) || $linkCode === ''
            || ! is_string($affiliateId) || $affiliateId === ''
            || ! is_string($offerId) || $offerId === ''
            || ! is_string($clickedAt) || $clickedAt === ''
            || ! is_string($signature) || $signature === '') {
            return null;
        }

        $expected = hash_hmac('sha256', implode('|', [
            $linkCode,
            $affiliateId,
            $offerId,
            $clickedAt,
            (string) ($attribution['sub1'] ?? ''),
            (string) ($attribution['sub2'] ?? ''),
            (string) ($attribution['sub3'] ?? ''),
        ]), (string) config('app.key'));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return [
            'code' => $linkCode,
            'affiliate_id' => $affiliateId,
            'offer_id' => $offerId,
            'clicked_at' => $clickedAt,
            'sub1' => is_string($attribution['sub1'] ?? null) ? $attribution['sub1'] : null,
            'sub2' => is_string($attribution['sub2'] ?? null) ? $attribution['sub2'] : null,
            'sub3' => is_string($attribution['sub3'] ?? null) ? $attribution['sub3'] : null,
        ];
    }

    private function withinWindow(string $clickedAt): bool
    {
        try {
            $clicked = CarbonImmutable::parse($clickedAt);
        } catch (Throwable) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($clicked->greaterThan($now->addMinutes(5))) {
            return false;
        }

        $windowHours = (int) config('affiliate-network.checkout.attribution_window_hours', 720);

        return $windowHours <= 0 || ! $clicked->addHours($windowHours)->isPast();
    }
}
