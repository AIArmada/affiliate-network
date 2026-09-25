<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Controllers\ReportNetworkConversionController;
use Illuminate\Support\Facades\Route;

// Redirects are served by aiarmada/links (/go/{slug}); the network only
// keeps the merchant conversion postback endpoint.

if (config('affiliate-network.postbacks.enabled', false)) {
    Route::prefix(config('affiliate-network.postbacks.prefix', 'api/affiliate-network'))
        ->middleware(config('affiliate-network.postbacks.middleware', ['api', 'throttle:60,1']))
        ->group(function (): void {
            Route::post('/conversions', ReportNetworkConversionController::class)
                ->name('affiliate-network.conversions.report');
        });
}
