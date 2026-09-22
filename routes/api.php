<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Controllers\LinkRedirectController;
use AIArmada\AffiliateNetwork\Http\Controllers\ReportNetworkConversionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function (): void {
    Route::get('/affiliate-network/go/{code}', LinkRedirectController::class)
        ->middleware(['signed', 'throttle:60,1'])
        ->name('affiliate-network.redirect');
});

if (config('affiliate-network.postbacks.enabled', false)) {
    Route::prefix(config('affiliate-network.postbacks.prefix', 'api/affiliate-network'))
        ->middleware(config('affiliate-network.postbacks.middleware', ['api', 'throttle:60,1']))
        ->group(function (): void {
            Route::post('/conversions', ReportNetworkConversionController::class)
                ->name('affiliate-network.conversions.report');
        });
}
