<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Actions;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Exceptions\ApplicationAlreadySubmittedException;
use AIArmada\AffiliateNetwork\Exceptions\RestrictionViolatedException;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Platform\AffiliateProfileGuard;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use RuntimeException;

final class ApplyToOffer
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly SitesApi $sites,
        private readonly AffiliateIdentityResolver $identities,
        private readonly AffiliateProfileGuard $affiliateProfileGuard,
    ) {}

    public function execute(
        string $offerId,
        string $affiliateId,
        ?string $reason = null,
        ?string $invitationToken = null,
        ?RestrictionContext $restrictionContext = null,
    ): AffiliateOfferApplication {
        $this->affiliateProfileGuard->assertCanWriteFor($affiliateId);

        $affiliate = $this->identities->findAccessible($affiliateId);

        if ($affiliate === null) {
            throw (new ModelNotFoundException)->setModel(
                NetworkAffiliate::class,
                [$affiliateId],
            );
        }

        $offer = $this->catalog->findOfferUnscoped($offerId);

        if (! $offer instanceof OfferRecord) {
            throw (new ModelNotFoundException)->setModel(
                (string) config('affiliate-network.models.offer', 'offer'),
                [$offerId],
            );
        }

        if ($offer->status !== OfferStatus::Published) {
            throw new RuntimeException('Only published offers accept applications.');
        }

        $site = $this->sites->findByIdUnscoped($offer->siteId);

        if (! $site instanceof SiteRecord || ! $this->sites->isVerified($offer->siteId)) {
            throw new RuntimeException('Applications are only accepted for verified sites.');
        }

        $invitation = $this->activeInvitation($offer, $affiliateId, $invitationToken);

        if ($offer->visibility !== OfferVisibility::Public && $invitation === null) {
            throw new RuntimeException('This offer is invite-only.');
        }

        if ($restrictionContext !== null) {
            $violation = $this->catalog->evaluateRestrictions($offer, $restrictionContext);

            if ($violation !== null) {
                throw RestrictionViolatedException::forOffer($offerId, $violation);
            }
        }

        $owner = OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId);
        $affiliateOwner = OwnerContext::fromTypeAndId($affiliate->ownerType, $affiliate->ownerId);
        $connection = (new AffiliateOfferApplication)->getConnection();

        return $connection->transaction(function () use ($offer, $site, $affiliateId, $reason, $invitation, $connection, $owner, $affiliateOwner): AffiliateOfferApplication {
            OwnerContext::withOwner($affiliateOwner, fn () => $this->affiliateProfileGuard->lockForWrite($affiliateId, spl_object_id($connection)));

            return OwnerContext::withOwner($owner, function () use ($offer, $site, $affiliateId, $reason, $invitation, $connection): AffiliateOfferApplication {
                return $connection->transaction(function () use ($offer, $site, $affiliateId, $reason, $invitation, $connection): AffiliateOfferApplication {
                    $existing = AffiliateOfferApplication::query()
                        ->where('offer_id', $offer->id)
                        ->where('affiliate_id', $affiliateId)
                        ->first();

                    if ($existing instanceof AffiliateOfferApplication) {
                        if ($existing->status === ApplicationStatus::Revoked) {
                            throw ApplicationAlreadySubmittedException::forOffer($offer->id);
                        }

                        if ($existing->status === ApplicationStatus::Rejected) {
                            $cooldownDays = (int) config('affiliate-network.applications.cooldown_days', 7);
                            $base = $existing->rejected_at ?? $existing->updated_at;
                            $canReapply = $base === null || CarbonImmutable::parse($base)->addDays($cooldownDays)->isPast();

                            if (! $canReapply) {
                                throw ApplicationAlreadySubmittedException::forOffer($offer->id);
                            }

                            $existing->update([
                                'status' => ApplicationStatus::Pending,
                                'reason' => $reason,
                                'rejection_reason' => null,
                                'reviewed_by' => null,
                                'reviewed_at' => null,
                            ]);

                            $application = $existing->fresh() ?? $existing;

                            event(new ApplicationSubmitted(...$this->eventPayload($application, $offer, $site)));

                            return $application;
                        }

                        return $existing;
                    }

                    $autoApprove = ! $offer->requiresApproval
                        || (bool) config('affiliate-network.applications.auto_approve', false)
                        || ($invitation?->auto_approve === true);

                    $status = $autoApprove ? ApplicationStatus::Approved : ApplicationStatus::Pending;

                    try {
                        return $connection->transaction(function () use ($offer, $site, $affiliateId, $reason, $invitation, $status, $connection): AffiliateOfferApplication {
                            $application = AffiliateOfferApplication::query()->create([
                                'offer_id' => $offer->id,
                                'affiliate_id' => $affiliateId,
                                'invitation_id' => $invitation?->getKey(),
                                'status' => $status,
                                'reason' => $reason,
                                'reviewed_at' => $status === ApplicationStatus::Approved ? CarbonImmutable::now() : null,
                                'approved_at' => $status === ApplicationStatus::Approved ? CarbonImmutable::now() : null,
                            ]);

                            event(new ApplicationSubmitted(...$this->eventPayload($application, $offer, $site)));

                            if ($status === ApplicationStatus::Approved) {
                                $connection->afterCommit(fn () => event(new ApplicationApproved(
                                    ...$this->eventPayload($application, $offer, $site),
                                )));
                            }

                            return $application;
                        });
                    } catch (QueryException $exception) {
                        if (! self::isUniqueConstraintViolation($exception)) {
                            throw $exception;
                        }

                        $raced = AffiliateOfferApplication::query()
                            ->where('offer_id', $offer->id)
                            ->where('affiliate_id', $affiliateId)
                            ->first();

                        if ($raced === null) {
                            throw $exception;
                        }

                        return $raced;
                    }
                }, attempts: 3);
            });
        }, attempts: 3);
    }

    /**
     * @return array{applicationId: string, offerId: string, offerName: string, siteId: string, affiliateId: string, status: string, ownerType: string|null, ownerId: string|int|null}
     */
    private function eventPayload(AffiliateOfferApplication $application, OfferRecord $offer, SiteRecord $site): array
    {
        return [
            'applicationId' => (string) $application->getKey(),
            'offerId' => $offer->id,
            'offerName' => $offer->name,
            'siteId' => $site->id,
            'affiliateId' => (string) $application->affiliate_id,
            'status' => $application->status->value,
            'ownerType' => $site->ownerType,
            'ownerId' => $site->ownerId,
        ];
    }

    private function activeInvitation(OfferRecord $offer, string $affiliateId, ?string $token): ?AffiliateOfferInvitation
    {
        $query = AffiliateOfferInvitation::query()
            ->withoutGlobalScopes()
            ->where('offer_id', $offer->id)
            ->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
            });

        if ($token !== null && $token !== '') {
            $query->where('token', $token);
        } else {
            $query->where('affiliate_id', $affiliateId);
        }

        $invitation = $query->first();

        return $invitation instanceof AffiliateOfferInvitation ? $invitation : null;
    }

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
