<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Platform;

use Illuminate\Contracts\Foundation\Application;

interface FeatureModule
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
