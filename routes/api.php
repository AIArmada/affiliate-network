<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Controllers\LinkRedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function (): void {
    Route::get('/affiliate-network/go/{code}', LinkRedirectController::class)
        ->middleware(['signed', 'throttle:60,1'])
        ->name('affiliate-network.redirect');
});
