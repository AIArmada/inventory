<?php

declare(strict_types=1);

use AIArmada\FilamentCustomers\FilamentCustomersPlugin;
use AIArmada\FilamentCustomers\FilamentCustomersServiceProvider;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;

it('FilamentCustomersPlugin registers resources and widgets', function (): void {
    $plugin = FilamentCustomersPlugin::make();

    expect($plugin->getId())->toBe('filament-customers');

    $panel = Mockery::mock(Panel::class);

    $panel
        ->shouldReceive('resources')
        ->once()
        ->with([
            AIArmada\FilamentCustomers\Resources\CustomerResource::class,
            AIArmada\FilamentCustomers\Resources\SegmentResource::class,
        ])
        ->andReturnSelf();

    $panel
        ->shouldReceive('pages')
        ->once()
        ->with([])
        ->andReturnSelf();

    $panel
        ->shouldReceive('widgets')
        ->once()
        ->with([
            AIArmada\FilamentCustomers\Widgets\CustomerStatsWidget::class,
            AIArmada\FilamentCustomers\Widgets\RecentCustomersWidget::class,
        ])
        ->andReturnSelf();

    $plugin->register($panel);
    $plugin->boot($panel);
});

it('FilamentCustomersServiceProvider publishes views', function (): void {
    $this->app->register(FilamentCustomersServiceProvider::class);

    $providerFile = (new ReflectionClass(FilamentCustomersServiceProvider::class))->getFileName();
    $sourceRaw = dirname($providerFile) . '/../resources/views';
    $sourceReal = realpath($sourceRaw);

    expect($sourceReal)->not->toBeFalse();

    $paths = ServiceProvider::pathsToPublish(FilamentCustomersServiceProvider::class, 'filament-customers-views');

    $matchedKey = collect(array_keys($paths))
        ->first(fn (string $path): bool => realpath($path) === $sourceReal);

    expect($matchedKey)->not->toBeNull();
    expect($paths[$matchedKey])->toBe(resource_path('views/vendor/filament-customers'));
});
