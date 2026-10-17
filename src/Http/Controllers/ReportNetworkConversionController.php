<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Exceptions\AffiliateProfileRequired;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Merchant conversion reporting.
 *
 * Remote merchants POST paid orders here; the network records the link
 * counters, an idempotent book leg, and triggers fulfillment. Merchants
 * authenticate with the site's catalog token. Reporting is idempotent
 * on (network link, external reference): redeliveries return the
 * existing state without touching counters.
 *
 * Trust boundary: authentication and site verification are enforced,
 * but reported revenue and touch times are merchant-asserted and taken
 * on the same trust basis.
 */
final class ReportNetworkConversionController extends Controller
{
    public function __invoke(Request $request, OfferLinkService $links): JsonResponse
    {
        $validated = $request->validate([
            'site' => ['required', 'string', 'max:255'],
            'link_code' => ['required', 'string', 'max:64'],
            'external_reference' => ['required', 'string', 'max:120'],
            'revenue_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'engine_touch_at' => ['present', 'nullable', 'date'],
            'network_clicked_at' => ['required', 'date'],
        ]);

        $engineTouchAt = isset($validated['engine_touch_at'])
            ? CarbonImmutable::parse($validated['engine_touch_at'])
            : null;
        $networkClickedAt = CarbonImmutable::parse($validated['network_clicked_at']);

        if (
            $networkClickedAt->greaterThan(CarbonImmutable::now()->addMinutes(5))
            || ($engineTouchAt !== null && $engineTouchAt->greaterThan(CarbonImmutable::now()->addMinutes(5)))
        ) {
            return response()->json(['message' => 'Attribution touches cannot be more than five minutes in the future.'], 422);
        }

        $site = OwnerContext::withOwner(null, fn (): ?AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey($validated['site'])
            ->orWhere('domain', $validated['site'])
            ->first());

        if (! $site instanceof AffiliateSite || ! $this->tokenMatches($site, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Invalid site credentials.'], 401);
        }

        if (! $site->isVerified()) {
            return response()->json(['message' => 'Site is not verified.'], 403);
        }

        $link = $links->resolveLink($validated['link_code']);

        if (! $link instanceof AffiliateOfferLink || (string) $link->site_id !== (string) $site->getKey()) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($link->isExpired() || ! $link->offer->isActive()) {
            return response()->json(['message' => 'Offer is no longer active.'], 410);
        }

        $existing = NetworkConversionLeg::query()
            ->where('link_id', $link->getKey())
            ->where('external_reference', $validated['external_reference'])
            ->first();

        if ($existing instanceof NetworkConversionLeg) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'network' => $links->getStats($link->refresh()),
                'conversion' => $existing->toArray(),
                'decision' => self::decisionPayload($existing),
            ]);
        }

        $currency = isset($validated['currency']) ? mb_strtoupper($validated['currency']) : null;
        $winner = $engineTouchAt !== null && $engineTouchAt->greaterThanOrEqualTo($networkClickedAt)
            ? 'engine'
            : 'network';
        $reason = $engineTouchAt === null ? 'engine_unattributed' : 'last_touch';
        $status = $winner === 'network' ? LegStatus::Posted : LegStatus::Superseded;

        try {
            $leg = $links->recordConversion(
                $link,
                (int) $validated['revenue_minor'],
                $currency,
                $validated['external_reference'],
                $status,
                [
                    'attribution_winner' => $winner,
                    'attribution_decision_reason' => $reason,
                ],
            );
        } catch (AffiliateProfileRequired $exception) {
            return response()->json([
                'error' => 'affiliate_profile_required',
                'message' => 'Affiliate profile required: ' . $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'duplicate' => false,
            'network' => $links->getStats($link->refresh()),
            'conversion' => $leg?->toArray(),
            'decision' => self::decisionPayload($leg),
        ]);
    }

    /**
     * @return array{winner: string, reason: string}|null
     */
    private static function decisionPayload(?NetworkConversionLeg $leg): ?array
    {
        if (! $leg instanceof NetworkConversionLeg) {
            return null;
        }

        $metadata = $leg->metadata ?? [];
        $winner = $metadata['attribution_winner'] ?? null;
        $reason = $metadata['attribution_decision_reason'] ?? null;

        if (! is_string($winner) || ! is_string($reason)) {
            return null;
        }

        return ['winner' => $winner, 'reason' => $reason];
    }

    private function tokenMatches(AffiliateSite $site, string $bearerToken): bool
    {
        if ($bearerToken === '' || empty($site->catalog_token_encrypted)) {
            return false;
        }

        try {
            $expected = decrypt($site->catalog_token_encrypted);
        } catch (Throwable) {
            return false;
        }

        return is_string($expected) && $expected !== '' && hash_equals($expected, $bearerToken);
    }
}
