<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LinkRedirectController
{
    public function __invoke(Request $request, string $code, OfferLinkService $linkService): RedirectResponse
    {
        $link = $linkService->resolveLink($code);

        if ($link === null) {
            abort(404, 'Link not found');
        }

        if ($link->isExpired()) {
            abort(410, 'Link has expired');
        }

        if (! $link->offer->isActive()) {
            abort(410, 'Offer is no longer active');
        }

        // Verification is a network fact: check keys unscoped instead of lazy
        // relations so ambient scope can never change a redirect decision.
        $linkSiteVerified = $link->site_id !== null && AffiliateSite::isVerifiedKey($link->site_id);
        $offerSiteVerified = AffiliateSite::isVerifiedKey($link->offer->site_id);

        if (! $linkSiteVerified && ! $offerSiteVerified) {
            abort(410, 'Offer is no longer active');
        }

        // Crawlers still get redirected, but bot hits don't inflate clicks.
        if (! self::isBot($request->userAgent())) {
            $linkService->recordClick($link);
        }

        $redirectUrl = $linkService->buildDirectLink($link);

        $scheme = mb_strtolower(parse_url($redirectUrl, PHP_URL_SCHEME) ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(400, 'Invalid redirect target');
        }

        return redirect()->away($redirectUrl);
    }

    private static function isBot(?string $userAgent): bool
    {
        if (! is_string($userAgent) || $userAgent === '') {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|mediabot|mediapartners|baidu|yandex|sogou|exabot|facebot|facebookexternalhit|ia_archiver|semrush|ahrefs|mj12bot|dotbot|petalbot/i', $userAgent) === 1;
    }
}
