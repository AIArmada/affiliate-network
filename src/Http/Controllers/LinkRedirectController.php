<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

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

        $linkService->recordClick($link);

        $redirectUrl = $linkService->buildDirectLink($link);

        $scheme = mb_strtolower(parse_url($redirectUrl, PHP_URL_SCHEME) ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(400, 'Invalid redirect target');
        }

        return redirect()->away($redirectUrl);
    }
}
