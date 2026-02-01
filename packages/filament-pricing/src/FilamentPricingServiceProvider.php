<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentPricingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-pricing')
            ->hasConfigFile('filament-pricing')
            ->hasViews('filament-pricing');
    }
}
