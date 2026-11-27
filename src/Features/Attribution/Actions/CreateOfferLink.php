<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Platform\AffiliateProfileGuard;
use AIArmada\AffiliateNetwork\Platform\DomainHostValidator;
use AIArmada\AffiliateNetwork\Platform\QueryParameters;
use AIArmada\Links\Actions\CreatePublicLink;
use AIArmada\Links\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class CreateOfferLink
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly EnrollmentApi $enrollments,
        private readonly AffiliateProfileGuard $affiliateProfileGuard,
        private readonly AffiliateIdentityResolver $identities,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function execute(string $offerId, string $affiliateId, array $options = []): AffiliateOfferLink
    {
        $this->affiliateProfileGuard->assertCanWriteFor($affiliateId);

        $offer = $this->catalog->findOfferUnscoped($offerId);

        if (! $offer instanceof OfferRecord) {
            throw new RuntimeException('Offer not found.');
        }

        if (! $offer->isActiveAt(CarbonImmutable::now())) {
            throw new RuntimeException('Links can only be created for active offers.');
        }

        if (! $this->sites->isVerified($offer->siteId)) {
            throw new RuntimeException('Links can only be created for verified sites.');
        }

        if ($offer->requiresApproval && ! $this->enrollments->isApprovedForOfferUnscoped($offer->id, $affiliateId)) {
            throw new RuntimeException('Affiliate is not approved for this offer.');
        }

        $site = $this->sites->findByIdUnscoped($offer->siteId);

        if ($site === null) {
            throw new RuntimeException('Offer site not found.');
        }

        $targetUrl = $this->resolveTargetUrl($offer, $site->domain, $options);

        $connection = (new AffiliateOfferLink)->getConnection();

        if ($connection !== (new Link)->getConnection()) {
            throw new RuntimeException('Offer tracking links require a shared database connection.');
        }

        return $connection->transaction(function () use ($offer, $affiliateId, $options, $targetUrl, $connection): AffiliateOfferLink {
            $this->affiliateProfileGuard->lockForWrite($affiliateId, spl_object_id($connection));

            $offerLink = AffiliateOfferLink::create([
                'offer_id' => $offer->id,
                'affiliate_id' => $affiliateId,
                'site_id' => $offer->siteId,
                'sub_id' => $options['sub_id'] ?? null,
                'sub_id_2' => $options['sub_id_2'] ?? null,
                'sub_id_3' => $options['sub_id_3'] ?? null,
                'currency' => $offer->currency,
                'is_active' => $options['is_active'] ?? true,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $link = $this->createTrackedLink($offerLink, $offer, $affiliateId, $targetUrl, $options);

            $offerLink->forceFill(['link_id' => $link->getKey()])->save();
            $offerLink->refresh();
            $offerLink->setRelation('link', $link);

            return $offerLink;
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveTargetUrl(OfferRecord $offer, string $domain, array $options): string
    {
        $requested = $options['target_url'] ?? null;

        if (is_string($requested) && $requested !== '' && ! $offer->allowDeepLinking) {
            throw new RuntimeException('Deep linking is disabled for this offer.');
        }

        $targetUrl = is_string($requested) && $requested !== ''
            ? $requested
            : ($offer->landingUrl ?? 'https://' . $domain . '/');

        if (! DomainHostValidator::isAllowedTargetUrl($targetUrl, $domain)) {
            throw new RuntimeException('Link target URL must belong to the verified site domain.');
        }

        return $targetUrl;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function createTrackedLink(
        AffiliateOfferLink $offerLink,
        OfferRecord $offer,
        string $affiliateId,
        string $targetUrl,
        array $options,
    ): Link {
        Validator::make($options, [
            'link_style' => ['sometimes', 'string', 'in:short,branded'],
            'link_label' => ['nullable', 'string', 'max:60'],
        ])->validate();

        $style = $options['link_style'] ?? config('affiliate-network.links.default_style', 'short');
        $identity = $style === 'branded' ? $this->identities->findAccessible($affiliateId) : null;
        $parameters = array_merge(
            QueryParameters::parse($options['custom_parameters'] ?? null),
            array_filter([
                'sub1' => $offerLink->sub_id,
                'sub2' => $offerLink->sub_id_2,
                'sub3' => $offerLink->sub_id_3,
            ]),
        );

        $link = CreatePublicLink::run([
            'name' => mb_substr(sprintf('%s / %s', $offer->name, mb_substr($affiliateId, 0, 8)), 0, 255),
            'destination_url' => $targetUrl,
            'parameters' => $parameters,
            'subject_type' => $offerLink->getMorphClass(),
            'subject_id' => (string) $offerLink->getKey(),
            'expires_at' => $options['expires_at'] ?? null,
        ], $style, $identity?->handle, $options['link_label'] ?? $offer->name, false, null, [
            (string) config('affiliate-network.links.parameter', 'anl'),
        ]);

        return $link;
    }
}
