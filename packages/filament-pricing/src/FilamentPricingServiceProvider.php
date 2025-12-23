<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing;

use Illuminate\Support\ServiceProvider;

class FilamentPricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-pricing');
    }
}
