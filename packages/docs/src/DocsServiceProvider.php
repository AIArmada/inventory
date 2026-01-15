<?php

declare(strict_types=1);

namespace AIArmada\Docs;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\Services\SequenceManager;
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
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        // Numbering registry
        $this->app->singleton(Numbering\NumberStrategyRegistry::class, Numbering\ConfiguredNumberStrategyRegistry::class);

        // Sequence manager
        $this->app->singleton(SequenceManager::class);

        // Register Doc Service (with both dependencies)
        $this->app->singleton(DocService::class, function ($app) {
            return new DocService(
                $app->make(Numbering\NumberStrategyRegistry::class),
                $app->make(SequenceManager::class),
            );
        });

        // Bind interface to implementation
        $this->app->alias(DocService::class, DocServiceInterface::class);
        $this->app->alias(DocService::class, 'doc');
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DocService::class,
            DocServiceInterface::class,
            SequenceManager::class,
            'doc',
            Numbering\NumberStrategyRegistry::class,
        ];
    }
}
