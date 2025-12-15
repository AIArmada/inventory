<?php

declare(strict_types=1);

namespace AIArmada\Docs;

use AIArmada\Docs\Services\DocService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DocsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('docs')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // Numbering registry
        $this->app->singleton(Numbering\NumberStrategyRegistry::class, Numbering\ConfiguredNumberStrategyRegistry::class);

        // Register Doc Service
        $this->app->singleton(DocService::class);
        $this->app->alias(DocService::class, 'doc');
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DocService::class,
            'doc',
            Numbering\NumberStrategyRegistry::class,
        ];
    }
}
