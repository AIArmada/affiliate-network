<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Actions;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\DeleteLinkForSubject;
use AIArmada\Links\Models\Link;
use LogicException;

final class DeleteOfferLinks
{
    public function execute(string $offerId): void
    {
        $connection = (new AffiliateOfferLink)->getConnection();

        if ($connection !== (new Link)->getConnection() || $connection !== (new AffiliateOfferCoupon)->getConnection()) {
            throw new LogicException('Offer link deletion requires a shared database connection.');
        }

        $connection->transaction(function () use ($offerId): void {
            $this->deleteForOffer($offerId);
        });
    }

    private function deleteForOffer(string $offerId): void
    {
        $links = AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->whereNotNull('link_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'link_id']);

        $trackedLinks = Link::query()
            ->withoutOwnerScope()
            ->whereIn('id', $links->pluck('link_id')->unique()->values())
            ->lockForUpdate()
            ->get(['id', 'owner_type', 'owner_id'])
            ->keyBy(static fn (Link $link): string => (string) $link->getKey());

        AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        AffiliateOfferCoupon::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offerId)
            ->delete();

        $delete = app(DeleteLinkForSubject::class);
        $subjectType = (new AffiliateOfferLink)->getMorphClass();

        foreach ($links as $link) {
            $tracked = $trackedLinks->get((string) $link->link_id);

            if (! $tracked instanceof Link) {
                continue;
            }

            $deleteLink = static fn (): bool => $delete->handle((string) $link->link_id, $subjectType, (string) $link->getKey());
            $owner = OwnerContext::fromTypeAndIdOrFail($tracked->owner_type, $tracked->owner_id);
            $deleted = OwnerContext::withOwner($owner, $deleteLink);

            if (! $deleted) {
                throw new LogicException('The tracked offer link could not be deleted.');
            }
        }
    }
}
