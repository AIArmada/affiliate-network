<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Events\OfferInvitationCreated;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

final class InviteAffiliateToOffer
{
    public function __construct(
        private readonly CatalogApi $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $offerId,
        ?string $affiliateId = null,
        ?string $invitedBy = null,
        ?CarbonImmutable $expiresAt = null,
        bool $autoApprove = true,
        array $metadata = [],
    ): AffiliateOfferInvitation {
        if ($this->catalog->findOfferUnscoped($offerId) === null) {
            throw (new ModelNotFoundException)->setModel(
                (string) config('affiliate-network.models.offer', 'offer'),
                [$offerId],
            );
        }

        $token = Str::random(40);

        $invitation = AffiliateOfferInvitation::query()->create([
            'offer_id' => $offerId,
            'affiliate_id' => $affiliateId,
            'token' => $token,
            'invited_by' => $invitedBy,
            'auto_approve' => $autoApprove,
            'expires_at' => $expiresAt,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        event(new OfferInvitationCreated(
            invitationId: (string) $invitation->getKey(),
            offerId: $offerId,
            affiliateId: $affiliateId,
            token: $token,
        ));

        return $invitation;
    }
}
