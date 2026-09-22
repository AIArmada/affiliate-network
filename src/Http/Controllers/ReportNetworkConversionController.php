<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Merchant conversion reporting.
 *
 * Remote merchants POST paid orders here; the network records the link
 * counters and the idempotent ledger row. Merchants authenticate with the
 * site's catalog token (shared secret, bearer). Reporting is idempotent
 * on (network link, external reference): redeliveries return the existing
 * state without touching counters.
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
        ]);

        $site = AffiliateSite::query()
            ->whereKey($validated['site'])
            ->orWhere('domain', $validated['site'])
            ->first();

        if (! $site instanceof AffiliateSite || ! $this->tokenMatches($site, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Invalid site credentials.'], 401);
        }

        $link = $links->resolveLink($validated['link_code']);

        if (! $link instanceof AffiliateOfferLink || (string) $link->site_id !== (string) $site->getKey()) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($link->isExpired() || ! $link->offer->isActive()) {
            return response()->json(['message' => 'Offer is no longer active.'], 410);
        }

        $existing = AffiliateConversion::query()
            ->where('network_link_id', $link->getKey())
            ->where('external_reference', $validated['external_reference'])
            ->first();

        if ($existing instanceof AffiliateConversion) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'network' => $links->getStats($link->refresh()),
                'conversion' => $this->conversionPayload($existing),
            ]);
        }

        $currency = isset($validated['currency']) ? mb_strtoupper($validated['currency']) : null;

        $links->recordConversion($link, (int) $validated['revenue_minor'], $currency, $validated['external_reference']);

        $conversion = AffiliateConversion::query()
            ->where('network_link_id', $link->getKey())
            ->where('external_reference', $validated['external_reference'])
            ->first();

        return response()->json([
            'ok' => true,
            'duplicate' => false,
            'network' => $links->getStats($link->refresh()),
            'conversion' => $conversion instanceof AffiliateConversion ? $this->conversionPayload($conversion) : null,
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function conversionPayload(AffiliateConversion $conversion): array
    {
        return [
            'id' => $conversion->id,
            'affiliate_code' => $conversion->affiliate_code,
            'commission_minor' => $conversion->commission_minor,
            'commission_currency' => $conversion->commission_currency,
            'status' => $conversion->status->getMorphClass(),
        ];
    }
}
