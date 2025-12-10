<?php

declare(strict_types=1);

use AIArmada\FilamentShipping\FilamentShippingPlugin;

// ============================================
// FilamentShippingPlugin Tests
// ============================================

it('creates plugin instance', function (): void {
    $plugin = FilamentShippingPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('returns correct plugin id', function (): void {
    $plugin = FilamentShippingPlugin::make();

    expect($plugin->getId())->toBe('filament-shipping');
});

it('can disable shipment resource', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable shipping zone resource', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shippingZoneResource(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable return authorization resource', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->returnAuthorizationResource(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable dashboard widgets', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->dashboardWidgets(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable shipping dashboard page', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shippingDashboard(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable manifest page', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->manifestPage(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('supports method chaining for configuration', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(true)
        ->shippingZoneResource(true)
        ->returnAuthorizationResource(true)
        ->dashboardWidgets(true)
        ->shippingDashboard(true)
        ->manifestPage(true);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable all features', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(false)
        ->shippingZoneResource(false)
        ->returnAuthorizationResource(false)
        ->dashboardWidgets(false)
        ->shippingDashboard(false)
        ->manifestPage(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});
