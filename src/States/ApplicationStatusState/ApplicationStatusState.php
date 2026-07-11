<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\States\ApplicationStatusState;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method AffiliateOfferApplication getModel()
 */
abstract class ApplicationStatusState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    public function isPending(): bool
    {
        return false;
    }

    public function isApproved(): bool
    {
        return false;
    }

    public function isRejected(): bool
    {
        return false;
    }

    public function isRevoked(): bool
    {
        return false;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ApprovedState::class)
            ->allowTransition(PendingState::class, RejectedState::class)
            ->allowTransition(RejectedState::class, PendingState::class)
            ->allowTransition(RejectedState::class, ApprovedState::class)
            ->allowTransition(ApprovedState::class, RevokedState::class);
    }
}
