<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\FilamentTaxPlugin;
use AIArmada\FilamentTax\Pages\ManageTaxSettings;
use AIArmada\FilamentTax\Resources\TaxClassResource;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\FilamentTax\Resources\TaxRateResource;
use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\FilamentTax\Widgets\ExpiringExemptionsWidget;
use AIArmada\FilamentTax\Widgets\TaxStatsWidget;
use AIArmada\FilamentTax\Widgets\ZoneCoverageWidget;
use Filament\Panel;

it('creates plugin instance', function (): void {
    $plugin = FilamentTaxPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentTaxPlugin::class);
});

it('returns correct plugin id', function (): void {
    $plugin = FilamentTaxPlugin::make();

    expect($plugin->getId())->toBe('filament-tax');
});

it('registers resources, widgets, and pages on the panel', function (): void {
    $plugin = FilamentTaxPlugin::make();

    $panel = Mockery::mock(Panel::class);

    $expectedPages = class_exists(\Filament\Pages\SettingsPage::class)
        ? [ManageTaxSettings::class]
        : [];

    $panel
        ->shouldReceive('resources')
        ->once()
        ->with([
            TaxZoneResource::class,
            TaxClassResource::class,
            TaxRateResource::class,
            TaxExemptionResource::class,
        ])
        ->andReturnSelf();

    $panel
        ->shouldReceive('widgets')
        ->once()
        ->with([
            TaxStatsWidget::class,
            ExpiringExemptionsWidget::class,
            ZoneCoverageWidget::class,
        ])
        ->andReturnSelf();

    $panel
        ->shouldReceive('pages')
        ->once()
        ->with($expectedPages)
        ->andReturnSelf();

    $plugin->register($panel);
});

it('respects plugin toggles when registering', function (): void {
    $plugin = FilamentTaxPlugin::make()
        ->zones(false)
        ->classes(false)
        ->rates(false)
        ->exemptions(false)
        ->widgets(false);

    $panel = Mockery::mock(Panel::class);

    $expectedPages = class_exists(\Filament\Pages\SettingsPage::class)
        ? [ManageTaxSettings::class]
        : [];

    $panel
        ->shouldReceive('resources')
        ->once()
        ->with([])
        ->andReturnSelf();

    $panel
        ->shouldReceive('widgets')
        ->once()
        ->with([])
        ->andReturnSelf();

    $panel
        ->shouldReceive('pages')
        ->once()
        ->with($expectedPages)
        ->andReturnSelf();

    $plugin->register($panel);
});
