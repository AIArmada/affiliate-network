<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferCouponRecord;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

final class VoidNetworkConversionController extends Controller
{
    public function __invoke(
        Request $request,
        SitesApi $sites,
        AttributionApi $attribution,
        LedgerApi $ledger,
        ReverseConversionLeg $reverseConversionLeg,
    ): JsonResponse {
        $siteIdentifier = $request->input('site');
        $site = is_string($siteIdentifier) && mb_trim($siteIdentifier) !== ''
            ? $sites->findByIdOrDomainUnscoped($siteIdentifier)
            : null;

        if (! $site instanceof SiteRecord || ! $sites->matchesCatalogToken($site->id, $request->bearerToken())) {
            return response()->json(['message' => 'Invalid site credentials.'], 401);
        }

        if (! $site->isVerified()) {
            return response()->json(['message' => 'Site is not verified.'], 403);
        }

        $validated = $request->validate([
            'site' => ['required', 'string', 'max:255'],
            'link_code' => ['required_without:coupon_code', 'nullable', 'string', 'max:64'],
            'coupon_code' => ['required_without:link_code', 'nullable', 'string', 'max:64'],
            'external_reference' => ['required', 'string', 'max:120'],
            'event_type' => ['nullable', 'string', 'max:64'],
            'reason' => ['present', 'nullable', 'string', 'max:120'],
        ]);

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
        $coupon = $couponCode === null ? null : $attribution->resolveCouponForSite($site->id, $couponCode);

        if ($link instanceof OfferLinkRecord && $link->siteId !== null && $link->siteId !== $site->id) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($linkCode !== null && ! $link instanceof OfferLinkRecord) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($couponCode !== null && ! $coupon instanceof OfferCouponRecord) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        $linkId = $link instanceof OfferLinkRecord ? $link->id : null;
        $resolvedCouponCode = $coupon instanceof OfferCouponRecord ? $coupon->code : null;

        $leg = $ledger->findLegByLinkOrCouponUnscoped($linkId, $resolvedCouponCode, $validated['external_reference'], $eventType);

        if ($leg === null) {
            return response()->json(['message' => 'Conversion not found.'], 404);
        }

        if ($leg->status === LegStatus::Provisional) {
            return response()->json([
                'message' => 'Conversion is provisional and owned by the shared checkout flow; it cannot be voided remotely.',
            ], 422);
        }

        $reason = isset($validated['reason']) && $validated['reason'] !== '' ? $validated['reason'] : 'refund';
        $duplicate = ! in_array($leg->status, [LegStatus::Pending, LegStatus::Posted], true);

        try {
            $reversal = $duplicate ? $leg : $reverseConversionLeg->execute($leg->id, $reason)->toSnapshot();
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'duplicate' => $duplicate,
            'conversion' => $reversal,
        ]);
    }
}
