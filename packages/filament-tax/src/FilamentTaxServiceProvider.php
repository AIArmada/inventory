<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentTaxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-tax')
            ->hasConfigFile()
            ->hasViews('filament-tax')
            ->hasTranslations();
    }
}
