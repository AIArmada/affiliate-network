<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Adapters\Affiliates;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Data\PostedConversion;

/**
 * Merchant books behind the network ledger seam.
 *
 * Maps network drafts onto merchant external conversions. Affiliate
 * resolution (merchant row, then verified-email fallback) lives in the
 * merchant service; unknown affiliates post nothing and the network
 * keeps books-only.
 */
final class AffiliatesLedgerPoster implements NetworkLedger
{
    public const SOURCE = 'marketplace';

    public function __construct(
        private readonly MerchantLedger $merchants,
        private readonly AffiliateIdentityResolver $identities,
    ) {}

    public function post(NetworkConversionDraft $draft): ?NetworkPostedConversion
    {
        $posted = $this->merchants->postExternalConversion(new ExternalConversion(
            source: self::SOURCE,
            sourceRef: $draft->linkId,
            affiliateId: $draft->affiliateId,
            revenueMinor: $draft->revenueMinor,
            currency: $draft->currency,
            externalReference: $draft->externalReference,
            commissionMinor: $draft->commissionMinor,
            affiliateEmail: $this->identities->find($draft->affiliateId)?->email,
            metadata: array_merge($draft->metadata, [
                'offer_id' => $draft->offerId,
                'link_code' => $draft->linkCode,
                'site_id' => $draft->siteId,
            ]),
        ));

        return $posted === null ? null : self::toNetworkPosted($posted);
    }

    public function findPosted(string $linkId, string $externalReference): ?NetworkPostedConversion
    {
        $posted = $this->merchants->findPosting(self::SOURCE, $linkId, $externalReference);

        return $posted === null ? null : self::toNetworkPosted($posted);
    }

    public function rowsForLink(string $linkId): array
    {
        return $this->merchants->postingsForSourceRef(self::SOURCE, $linkId);
    }

    public function postingsForExternalReference(string $externalReference): array
    {
        return $this->merchants->postingsForExternalReference($externalReference);
    }

    private static function toNetworkPosted(PostedConversion $posted): NetworkPostedConversion
    {
        return new NetworkPostedConversion(
            id: $posted->id,
            affiliateCode: $posted->affiliateCode,
            commissionMinor: $posted->commissionMinor,
            commissionCurrency: $posted->commissionCurrency,
            status: $posted->status,
        );
    }
}
