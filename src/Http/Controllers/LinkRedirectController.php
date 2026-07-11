<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

use AIArmada\AffiliateNetwork\Exceptions\UnauthorizedRedirectTargetException;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\RedirectHostValidator;
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

        $targetUrl = $link->target_url;

        try {
            app(RedirectHostValidator::class)->validate(
                targetUrl: $targetUrl,
                siteDomain: $link->site->domain,
                siteAllowedHosts: $link->site->allowed_redirect_hosts ?? [],
                offerAllowedHosts: $link->offer->restrictions['allowed_redirect_hosts'] ?? [],
            );
        } catch (UnauthorizedRedirectTargetException $e) {
            abort(400, $e->getMessage());
        }

        return redirect()->away($linkService->buildDirectLink($link));
    }
}
