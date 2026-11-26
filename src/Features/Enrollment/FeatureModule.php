<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment;

use AIArmada\AffiliateNetwork\Contracts\AffiliateReferenceReader;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\EntryPoints\Listeners\NotifyApplicationApproved;
use AIArmada\AffiliateNetwork\Features\Enrollment\EntryPoints\Listeners\NotifyApplicationSubmitted;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Enrollment\Queries\EloquentEnrollmentApi;
use AIArmada\AffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->tag([Queries\EnrollmentAffiliateReferences::class], AffiliateReferenceReader::class);
        $app->bind(EnrollmentApi::class, EloquentEnrollmentApi::class);

        config([
            'affiliate-network.models.offer_application' => AffiliateOfferApplication::class,
            'affiliate-network.models.offer_invitation' => AffiliateOfferInvitation::class,
            'affiliate-network.models.offer_deal' => AffiliateOfferDeal::class,
        ]);
    }

    public function boot(Application $app): void
    {
        Event::listen(ApplicationSubmitted::class, NotifyApplicationSubmitted::class);
        Event::listen(ApplicationApproved::class, NotifyApplicationApproved::class);
    }
}
