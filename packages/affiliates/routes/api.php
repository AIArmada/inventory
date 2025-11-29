<?php

declare(strict_types=1);

use AIArmada\Affiliates\Http\Controllers\AffiliateApiController;
use Illuminate\Support\Facades\Route;

if (! config('affiliates.api.enabled', false)) {
    return;
}

Route::prefix(config('affiliates.api.prefix', 'api/affiliates'))
    ->middleware(config('affiliates.api.middleware', ['api']))
    ->group(function (): void {
        Route::middleware(AIArmada\Affiliates\Support\Middleware\EnsureApiAuthorized::class)->group(function (): void {
            Route::get('{code}/summary', [AffiliateApiController::class, 'summary']);
            Route::get('{code}/links', [AffiliateApiController::class, 'links']);
            Route::get('{code}/creatives', [AffiliateApiController::class, 'creatives']);
        });
    });
