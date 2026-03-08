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

        return redirect()->away($redirectUrl);
    }
}
