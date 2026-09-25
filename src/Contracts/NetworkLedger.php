<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;

/**
 * Persist network conversions to the system of record for money.
 *
 * Posting is idempotent on (network link, external reference):
 * redeliveries return the existing posting. A null return means the
 * draft references an unknown affiliate and nothing was posted.
 */
interface NetworkLedger
{
    public function post(NetworkConversionDraft $draft): ?NetworkPostedConversion;

    public function findPosted(string $linkId, string $externalReference): ?NetworkPostedConversion;

    /**
     * Ledger legs for one link, for counter reconciliation.
     *
     * @return array<int, array{commission_currency: string|null, value_minor: int, commission_minor: int}>
     */
    public function rowsForLink(string $linkId): array;

    /**
     * Merchant postings sharing one external reference, all origins.
     *
     * @return array<int, array{origin: string|null, source_ref: string|null, commission_currency: string|null, commission_minor: int}>
     */
    public function postingsForExternalReference(string $externalReference): array;
}
