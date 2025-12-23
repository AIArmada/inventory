<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\FilamentPricingPlugin;
use Filament\FilamentManager;
use Filament\Panel;

it('exposes a stable plugin id', function (): void {
    $plugin = app(FilamentPricingPlugin::class);

    expect($plugin->getId())->toBe('filament-pricing');
});

it('can be resolved via make()', function (): void {
    $plugin = FilamentPricingPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentPricingPlugin::class);
    expect($plugin->getId())->toBe('filament-pricing');
});

it('resolves via filament() helper in get()', function (): void {
    $expectedPlugin = app(FilamentPricingPlugin::class);

    $originalFilament = app()->bound('filament') ? app('filament') : null;

    $filament = Mockery::mock(FilamentManager::class);
    $filament->shouldReceive('getPlugin')
        ->once()
        ->with('filament-pricing')
        ->andReturn($expectedPlugin);

    app()->instance('filament', $filament);

    expect(FilamentPricingPlugin::get())->toBe($expectedPlugin);

    if ($originalFilament) {
        app()->instance('filament', $originalFilament);
    } else {
        app()->forgetInstance('filament');
    }
});

it('registers resources and widgets on the panel', function (): void {
    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')->once()->with([
        AIArmada\FilamentPricing\Resources\PriceListResource::class,
        AIArmada\FilamentPricing\Resources\PromotionResource::class,
    ])->andReturnSelf();

    $expectedPages = [
        AIArmada\FilamentPricing\Pages\ManagePricingSettings::class,
    ];

    if (class_exists('\\AIArmada\\Products\\Models\\Product') && class_exists('\\AIArmada\\Products\\Models\\Variant')) {
        $expectedPages[] = AIArmada\FilamentPricing\Pages\PriceSimulator::class;
    }

    $panel->shouldReceive('pages')->once()->with($expectedPages)->andReturnSelf();

    $panel->shouldReceive('widgets')->once()->with([
        AIArmada\FilamentPricing\Widgets\PricingStatsWidget::class,
    ])->andReturnSelf();

    $plugin = app(FilamentPricingPlugin::class);
    $plugin->register($panel);
});
