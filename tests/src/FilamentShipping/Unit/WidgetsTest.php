<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Widgets\CarrierPerformanceWidget;
use AIArmada\FilamentShipping\Widgets\PendingActionsWidget;
use AIArmada\FilamentShipping\Widgets\PendingShipmentsWidget;
use AIArmada\FilamentShipping\Widgets\ShippingDashboardWidget;

uses(TestCase::class);

// ============================================
// Filament Shipping Widgets Tests
// ============================================

describe('ShippingDashboardWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new ShippingDashboardWidget;

        expect($widget)->toBeInstanceOf(ShippingDashboardWidget::class);
    });

    it('has polling enabled', function (): void {
        $widget = new ShippingDashboardWidget;

        $reflection = new ReflectionProperty($widget, 'pollingInterval');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('30s');
    });
});

describe('PendingShipmentsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PendingShipmentsWidget;

        expect($widget)->toBeInstanceOf(PendingShipmentsWidget::class);
    });

    it('spans full width', function (): void {
        $widget = new PendingShipmentsWidget;

        $reflection = new ReflectionProperty($widget, 'columnSpan');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('full');
    });
});

describe('CarrierPerformanceWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new CarrierPerformanceWidget;

        expect($widget)->toBeInstanceOf(CarrierPerformanceWidget::class);
    });

    it('has longer polling interval', function (): void {
        $widget = new CarrierPerformanceWidget;

        $reflection = new ReflectionProperty($widget, 'pollingInterval');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('60s');
    });

    it('spans full width', function (): void {
        $widget = new CarrierPerformanceWidget;

        $reflection = new ReflectionProperty($widget, 'columnSpan');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('full');
    });
});

describe('PendingActionsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PendingActionsWidget;

        expect($widget)->toBeInstanceOf(PendingActionsWidget::class);
    });

    it('has polling enabled', function (): void {
        $widget = new PendingActionsWidget;

        $reflection = new ReflectionProperty($widget, 'pollingInterval');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('30s');
    });
});
