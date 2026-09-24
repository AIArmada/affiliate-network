<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services\Catalog;

use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

/**
 * Pick the catalog reader for a site: remote HTTP when the site owner
 * configured catalog_url, otherwise the local shared-DB reader.
 *
 * The local reader is bound by the affiliates package under a string key
 * so this module never references its class.
 */
final class CatalogReaderResolver
{
    public const string LOCAL_READER_KEY = 'affiliate-network.catalog.local-reader';

    public function __construct(
        private readonly RemoteCatalogClient $remote,
    ) {}

    public function readerFor(AffiliateSite $site): CatalogReaderInterface
    {
        if (! empty($site->catalog_url)) {
            return $this->remote;
        }

        if (! app()->bound(self::LOCAL_READER_KEY)) {
            throw AffiliatesNotInstalled::forFeature('local catalog sync');
        }

        $reader = app(self::LOCAL_READER_KEY);

        if (! $reader instanceof CatalogReaderInterface) {
            throw AffiliatesNotInstalled::forFeature('local catalog sync');
        }

        return $reader;
    }
}
