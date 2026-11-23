<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Queries;

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CreativeRecord;
use AIArmada\Links\Models\Link;
use RuntimeException;

final class CreativePayloadQuery
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly ResolveOfferLinkQuery $links,
    ) {}

    /**
     * @return array{id: string, type: string, name: string, description: string|null, asset_url: string|null, tracking_url: string, embed_code: string|null, width: int|null, height: int|null}
     */
    public function execute(string $creativeId, string $linkId): array
    {
        $creative = $this->catalog->findCreativeUnscoped($creativeId);

        if (! $creative instanceof CreativeRecord) {
            throw new RuntimeException('Creative not found.');
        }

        $link = $this->links->byIdUnscoped($linkId);

        if (! $link instanceof AffiliateOfferLink || ! $link->is_active) {
            throw new RuntimeException('Offer link not found.');
        }

        $linkRecord = $link->toRecord();
        $creativeIdInLink = $linkRecord->metadata['creative_id'] ?? null;

        if ($linkRecord->offerId !== $creative->offerId || $creativeIdInLink !== $creativeId) {
            throw new RuntimeException('The link must belong to this active creative.');
        }

        if (! $creative->isActive) {
            throw new RuntimeException('The creative is not active.');
        }

        $trackingUrl = $this->trackingUrl($link);

        return [
            'id' => $creative->id,
            'type' => $creative->type->value,
            'name' => $creative->name,
            'description' => $creative->description,
            'asset_url' => $creative->sourceAssetUrl,
            'tracking_url' => $trackingUrl,
            'embed_code' => $this->embedCode($creative, $trackingUrl),
            'width' => $creative->width,
            'height' => $creative->height,
        ];
    }

    private function trackingUrl(AffiliateOfferLink $link): string
    {
        $tracked = $link->link_id === null
            ? null
            : Link::query()->withoutOwnerScope()->whereKey($link->link_id)->first();

        if (! $tracked instanceof Link) {
            throw new RuntimeException('Offer link has no tracked link.');
        }

        return $tracked->cloakedUrl();
    }

    private function embedCode(CreativeRecord $creative, string $trackingUrl): ?string
    {
        $href = htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($creative->name, ENT_QUOTES, 'UTF-8');
        $asset = $creative->sourceAssetUrl;

        if (in_array($creative->type, [CreativeType::Banner, CreativeType::Image], true)) {
            if ($asset === null) {
                return null;
            }

            $src = htmlspecialchars($asset, ENT_QUOTES, 'UTF-8');

            return sprintf('<a href="%s" rel="sponsored"><img src="%s" alt="%s"></a>', $href, $src, $name);
        }

        if (in_array($creative->type, [CreativeType::Html, CreativeType::Email], true)
            && $creative->htmlCode !== null) {
            return str_replace('{{tracking_url}}', $href, $creative->htmlCode);
        }

        return sprintf('<a href="%s" rel="sponsored">%s</a>', $href, $name);
    }
}
