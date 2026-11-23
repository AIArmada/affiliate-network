<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\AdjustNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\VoidNetworkConversionController;
use Illuminate\Support\Facades\Route;

if (config('affiliate-network.postbacks.enabled', false)) {
    Route::post('/conversions', ReportNetworkConversionController::class)
        ->name('affiliate-network.conversions.report');
    Route::post('/conversions/void', VoidNetworkConversionController::class)
        ->name('affiliate-network.conversions.void');
    Route::post('/conversions/adjust', AdjustNetworkConversionController::class)
        ->name('affiliate-network.conversions.adjust');
}
