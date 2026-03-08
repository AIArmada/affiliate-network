<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Controllers\LinkRedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function (): void {
    Route::get('/affiliate-network/go/{code}', LinkRedirectController::class)
        ->name('affiliate-network.redirect');
});
