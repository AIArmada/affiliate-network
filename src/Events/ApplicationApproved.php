<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Events;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ApplicationApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public AffiliateOfferApplication $application,
    ) {}
}
