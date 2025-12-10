<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping;

use AIArmada\FilamentShipping\Services\CartBridge;
use Illuminate\Support\ServiceProvider;

class FilamentShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartBridge::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-shipping');
    }
}
