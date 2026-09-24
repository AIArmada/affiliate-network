<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
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
    public function __construct(
        private readonly ?NetworkLedger $ledger = null,
    ) {}

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

        $existing = $this->ledger?->findPosted((string) $link->getKey(), $validated['external_reference']);

        if ($existing !== null) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'network' => $links->getStats($link->refresh()),
                'conversion' => $existing->toArray(),
            ]);
        }

        $currency = isset($validated['currency']) ? mb_strtoupper($validated['currency']) : null;

        $links->recordConversion($link, (int) $validated['revenue_minor'], $currency, $validated['external_reference']);

        $conversion = $this->ledger?->findPosted((string) $link->getKey(), $validated['external_reference']);

        return response()->json([
            'ok' => true,
            'duplicate' => false,
            'network' => $links->getStats($link->refresh()),
            'conversion' => $conversion?->toArray(),
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
}
