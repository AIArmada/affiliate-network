<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Exceptions\AffiliateProfileRequired;
use AIArmada\AffiliateNetwork\Exceptions\CapExceededException;
use AIArmada\AffiliateNetwork\Exceptions\RestrictionViolatedException;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferCouponRecord;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ReportNetworkConversionController extends Controller
{
    public function __invoke(
        Request $request,
        SitesApi $sites,
        AttributionApi $attribution,
        CatalogApi $catalog,
        LedgerApi $ledger,
        RecordNetworkConversion $recordConversion,
    ): JsonResponse {
        $validated = $request->validate([
            'site' => ['required', 'string', 'max:255'],
            'link_code' => ['required_without:coupon_code', 'nullable', 'string', 'max:64'],
            'coupon_code' => ['required_without:link_code', 'nullable', 'string', 'max:64'],
            'external_reference' => ['required', 'string', 'max:120'],
            'revenue_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'event_type' => ['nullable', 'string', 'max:64'],
            'sub_id' => ['nullable', 'string', 'max:255'],
            'sub_id_2' => ['nullable', 'string', 'max:255'],
            'sub_id_3' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:2'],
            'channel' => ['nullable', 'string', 'max:64'],
            'device' => ['nullable', 'string', 'max:32'],
            'engine_touch_at' => ['present', 'nullable', 'date'],
            'network_clicked_at' => ['required', 'date'],
        ]);

        $networkClickedAt = CarbonImmutable::parse($validated['network_clicked_at']);
        $engineTouchAt = isset($validated['engine_touch_at'])
            ? CarbonImmutable::parse($validated['engine_touch_at'])
            : null;

        if ($networkClickedAt->greaterThan(CarbonImmutable::now()->addMinutes(5))
            || ($engineTouchAt !== null && $engineTouchAt->greaterThan(CarbonImmutable::now()->addMinutes(5)))) {
            return response()->json(['message' => 'Attribution touches cannot be more than five minutes in the future.'], 422);
        }

        $site = $sites->findByIdOrDomainUnscoped($validated['site']);

        if (! $site instanceof SiteRecord || ! $sites->matchesCatalogToken($site->id, $request->bearerToken())) {
            return response()->json(['message' => 'Invalid site credentials.'], 401);
        }

        if (! $site->isVerified()) {
            return response()->json(['message' => 'Site is not verified.'], 403);
        }

        $eventType = is_string($validated['event_type'] ?? null) && $validated['event_type'] !== ''
            ? $validated['event_type']
            : 'purchase';

        $linkCode = is_string($validated['link_code'] ?? null) && $validated['link_code'] !== ''
            ? $validated['link_code']
            : null;
        $couponCode = is_string($validated['coupon_code'] ?? null) && $validated['coupon_code'] !== ''
            ? $validated['coupon_code']
            : null;

        $link = $linkCode === null ? null : $attribution->findLinkBySlugUnscoped($linkCode);
        $coupon = $couponCode === null ? null : $attribution->resolveCouponForSite($site->id, $couponCode, $networkClickedAt);

        if ($linkCode !== null && ! $link instanceof OfferLinkRecord) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($link instanceof OfferLinkRecord && $link->siteId !== null && $link->siteId !== $site->id) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($couponCode !== null && ! $coupon instanceof OfferCouponRecord) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        $offerId = $link instanceof OfferLinkRecord
            ? $link->offerId
            : ($coupon instanceof OfferCouponRecord ? $coupon->offerId : null);
        $affiliateId = $link instanceof OfferLinkRecord
            ? $link->affiliateId
            : ($coupon instanceof OfferCouponRecord ? $coupon->affiliateId : null);

        if ($offerId === null || $affiliateId === null) {
            return response()->json(['message' => 'Conversion target not resolvable.'], 404);
        }

        if (! $catalog->wasOfferActiveAt($offerId, $networkClickedAt)) {
            return response()->json(['message' => 'Offer was not active at the click time.'], 410);
        }

        $idempotencyKey = ($link instanceof OfferLinkRecord ? 'leg:link:' . $link->id : 'leg:coupon:')
            . ':' . $validated['external_reference']
            . ':' . $eventType;

        $existing = $ledger->findLegByIdempotencyKeyUnscoped($idempotencyKey);

        if ($existing !== null) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'network' => $link instanceof OfferLinkRecord
                    ? $attribution->linkStats($link->id)
                    : null,
                'conversion' => $existing,
                'decision' => [
                    'attribution_winner' => $existing->metadata['attribution_winner'] ?? 'network',
                    'attribution_decision_reason' => $existing->metadata['attribution_decision_reason'] ?? 'engine_unattributed',
                ],
            ]);
        }

        $currency = isset($validated['currency']) ? mb_strtoupper($validated['currency']) : null;
        $status = $this->winningStatus($engineTouchAt, $networkClickedAt);

        try {
            $leg = $recordConversion->execute(
                offerId: $offerId,
                affiliateId: $affiliateId,
                revenueMinor: (int) $validated['revenue_minor'],
                currency: $currency,
                externalReference: $validated['external_reference'],
                eventType: $eventType,
                status: $status,
                linkId: $link instanceof OfferLinkRecord ? $link->id : null,
                couponCode: $coupon instanceof OfferCouponRecord ? $coupon->code : null,
                linkCode: $link instanceof OfferLinkRecord ? $link->slug : null,
                subId: $validated['sub_id'] ?? null,
                subId2: $validated['sub_id_2'] ?? null,
                subId3: $validated['sub_id_3'] ?? null,
                metadata: $this->decisionMetadata($engineTouchAt, $networkClickedAt),
            );
        } catch (AffiliateProfileRequired $exception) {
            return response()->json([
                'error' => 'affiliate_profile_required',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (CapExceededException | RestrictionViolatedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'duplicate' => false,
            'network' => $link instanceof OfferLinkRecord
                ? $attribution->linkStats($link->id)
                : null,
            'conversion' => $leg->toSnapshot(),
            'decision' => $this->decisionMetadata($engineTouchAt, $networkClickedAt),
        ]);
    }

    private function winningStatus(?CarbonImmutable $engineTouchAt, CarbonImmutable $networkClickedAt): LegStatus
    {
        if ($engineTouchAt !== null && $engineTouchAt->greaterThanOrEqualTo($networkClickedAt)) {
            return LegStatus::Superseded;
        }

        return LegStatus::Posted;
    }

    /**
     * @return array{attribution_winner: string, attribution_decision_reason: string}
     */
    private function decisionMetadata(?CarbonImmutable $engineTouchAt, CarbonImmutable $networkClickedAt): array
    {
        if ($engineTouchAt === null) {
            return ['attribution_winner' => 'network', 'attribution_decision_reason' => 'engine_unattributed'];
        }

        $engineWins = $engineTouchAt->greaterThanOrEqualTo($networkClickedAt);

        return [
            'attribution_winner' => $engineWins ? 'engine' : 'network',
            'attribution_decision_reason' => $engineWins ? 'last_touch' : 'network_last_touch',
        ];
    }
}
