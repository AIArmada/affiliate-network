<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\AffiliateNetwork\Support\QueryParameters;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\GenerateLinkUrl;
use AIArmada\Links\Contracts\SlugGeneratorInterface;
use AIArmada\Links\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Offer Link Service — link lifecycle.
 *
 * BOUNDARY: This service owns attribution records for affiliate/offer pairs:
 * minting links for approved offers, resolving them by slug, and recording
 * conversions. Redirect mechanics (slugs, signed URLs, click capture) live in
 * aiarmada/links; each offer link rides on one backing tracked link.
 *
 * @see OfferManagementService for offer lifecycle operations.
 */
final class OfferLinkService
{
    public function __construct(
        private readonly RecordNetworkConversion $recordNetworkConversionAction,
        private readonly OfferManagementService $offerManagementService,
        private readonly SlugGeneratorInterface $slugs,
        private readonly ?AffiliateIdentityResolver $identities = null,
    ) {}

    /**
     * Create a deep link for an affiliate to promote an offer.
     *
     * @param  array<string, mixed>  $options
     */
    public function createLink(
        AffiliateOffer $offer,
        string $affiliateId,
        array $options = []
    ): AffiliateOfferLink {
        if (! $offer->isActive()) {
            throw new RuntimeException('Links can only be created for active offers.');
        }

        if (! AffiliateSite::isVerifiedKey($offer->site_id)) {
            throw new RuntimeException('Links can only be created for verified sites.');
        }

        if ($offer->requires_approval && ! $this->offerManagementService->isApprovedForOffer($offer, $affiliateId)) {
            throw new RuntimeException('Affiliate is not approved for this offer.');
        }

        $targetUrl = $options['target_url'] ?? $offer->landing_url ?? "https://{$offer->site->domain}/";

        if (! self::isHttpUrl($targetUrl)) {
            throw new RuntimeException('Link target URL must be a valid http(s) URL.');
        }

        return DB::transaction(function () use ($offer, $affiliateId, $options, $targetUrl): AffiliateOfferLink {
            $offerLink = AffiliateOfferLink::create([
                'offer_id' => $offer->id,
                'affiliate_id' => $affiliateId,
                'site_id' => $offer->site_id,
                'sub_id' => $options['sub_id'] ?? null,
                'sub_id_2' => $options['sub_id_2'] ?? null,
                'sub_id_3' => $options['sub_id_3'] ?? null,
                'currency' => $offer->currency,
                'is_active' => $options['is_active'] ?? true,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $link = $this->createTrackedLink($offerLink, $offer, $affiliateId, $targetUrl, $options);

            $offerLink->forceFill(['link_id' => $link->id])->save();
            $offerLink->refresh();
            $offerLink->setRelation('link', $link);

            return $offerLink;
        });
    }

    /**
     * Generate a tracking URL for a link.
     */
    public function generateTrackingUrl(AffiliateOfferLink $link): string
    {
        $tracked = $link->link;

        if (! $tracked instanceof Link) {
            throw new RuntimeException('Offer link has no tracked link.');
        }

        return GenerateLinkUrl::run($tracked);
    }

    /**
     * Resolve a link by its tracked slug.
     *
     * This is a public attribution surface — links are globally accessible by
     * slug without an owner context. Explicit global scope bypass is required.
     */
    public function resolveLink(string $slug): ?AffiliateOfferLink
    {
        // Public attribution intentionally resolves by slug in an explicit
        // global window. Affiliate identity is never eager-loaded here so
        // resolution works without the affiliates engine installed.
        return OwnerContext::withOwner(null, fn (): ?AffiliateOfferLink => AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->where('is_active', true)
            ->whereHas('link', fn ($query) => $query->withoutOwnerScope()->where('slug', $slug))
            ->with([
                'link' => fn ($query) => $query->withoutOwnerScope(),
                'offer' => fn ($query) => $query->withoutGlobalScope(ScopesByBelongsToOwner::class),
                'site' => fn ($query) => $query->withoutOwnerScope(),
            ])
            ->first());
    }

    /**
     * Record a conversion on a link.
     */
    public function recordConversion(
        AffiliateOfferLink $link,
        int $revenueMinor = 0,
        ?string $currency = null,
        ?string $externalReference = null,
        LegStatus $status = LegStatus::Posted,
    ): ?NetworkConversionLeg {
        return $this->withLinkOwnerContext($link, function () use ($link, $revenueMinor, $currency, $externalReference, $status): ?NetworkConversionLeg {
            return $this->recordNetworkConversionAction->execute($link, $revenueMinor, $currency, $externalReference, $status);
        });
    }

    /**
     * Get statistics for a link.
     *
     * @return array<string, mixed>
     */
    public function getStats(AffiliateOfferLink $link): array
    {
        $conversionRate = $link->clicks > 0
            ? round(($link->conversions / $link->clicks) * 100, 2)
            : 0.0;

        $revenuePerClick = $link->clicks > 0
            ? round($link->revenue / $link->clicks, 2)
            : 0.0;

        return [
            'clicks' => $link->clicks,
            'conversions' => $link->conversions,
            'revenue' => $link->revenue,
            'currency' => $link->currency,
            'formatted_revenue' => MoneyFormatter::formatMinor($link->revenue, $link->currency ?? 'MYR'),
            'conversion_rate' => $conversionRate,
            'revenue_per_click' => $revenuePerClick,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function createTrackedLink(AffiliateOfferLink $offerLink, AffiliateOffer $offer, string $affiliateId, string $targetUrl, array $options): Link
    {
        $param = config('affiliate-network.links.parameter', 'anl');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $slug = $this->slugs->generate(16);

            $parameters = array_merge(
                QueryParameters::parse($options['custom_parameters'] ?? null),
                [$param => $slug],
                array_filter([
                    'sub1' => $offerLink->sub_id,
                    'sub2' => $offerLink->sub_id_2,
                    'sub3' => $offerLink->sub_id_3,
                ]),
            );

            try {
                // Merchants may still serve plain http, so the network opts its
                // own links out of the links default https requirement.
                return CreateLink::run([
                    'name' => mb_substr(sprintf('%s / %s', $offer->name, mb_substr($affiliateId, 0, 8)), 0, 255),
                    'slug' => $slug,
                    'destination_url' => $targetUrl,
                    'parameters' => $parameters,
                    'require_signature' => true,
                    'subject_type' => $offerLink->getMorphClass(),
                    'subject_id' => (string) $offerLink->getKey(),
                    'expires_at' => $options['expires_at'] ?? null,
                ], false);
            } catch (ValidationException $exception) {
                if (! isset($exception->errors()['slug'])) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to mint a unique tracked slug.');
    }

    private static function isHttpUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(mb_strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withLinkOwnerContext(AffiliateOfferLink $link, callable $callback): mixed
    {
        $owner = $this->identities?->find((string) $link->affiliate_id)?->owner();

        return OwnerContext::withOwner($owner, $callback);
    }
}
