<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CreativeRecord;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use Carbon\CarbonImmutable;
use RuntimeException;

final class CreateCreativeLink
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly CreateOfferLink $createOfferLink,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function execute(string $creativeId, string $affiliateId, array $options = []): AffiliateOfferLink
    {
        $creative = $this->catalog->findCreativeUnscoped($creativeId);

        if (! $creative instanceof CreativeRecord) {
            throw new RuntimeException('Creative not found.');
        }

        if (! $creative->isActive) {
            throw new RuntimeException('Links can only be created for active creatives.');
        }

        $offer = $this->catalog->findOfferUnscoped($creative->offerId);

        if (! $offer instanceof OfferRecord || ! $offer->isActiveAt(CarbonImmutable::now())) {
            throw new RuntimeException('Links can only be created for active offers.');
        }

        $site = $this->sites->findByIdUnscoped($offer->siteId);

        if ($site === null) {
            throw new RuntimeException('Offer site not found.');
        }

        $options['target_url'] = $creative->destinationUrl ?? $offer->landingUrl ?? 'https://' . $site->domain . '/';
        $options['metadata'] = array_merge($options['metadata'] ?? [], ['creative_id' => $creative->id]);

        return $this->createOfferLink->execute($offer->id, $affiliateId, $options);
    }
}
