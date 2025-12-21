<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

$middleware = [];

if ((bool) config('chip.webhooks.verify_signature', true)) {
    $middleware[] = VerifyWebhookSignature::class;
}

Route::post(config('chip.webhooks.route', '/chip/webhook'), [WebhookController::class, 'handle'])
    ->middleware($middleware)
    ->name('chip.webhook');
