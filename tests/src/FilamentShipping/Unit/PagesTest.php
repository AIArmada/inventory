<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Pages\ManifestPage;
use AIArmada\FilamentShipping\Pages\ShippingDashboard;
use Filament\Support\Icons\Heroicon;

uses(TestCase::class);

// ============================================
// Filament Shipping Pages Tests
// ============================================

describe('ShippingDashboard', function (): void {
    it('has correct navigation icon', function (): void {
        $reflection = new ReflectionProperty(ShippingDashboard::class, 'navigationIcon');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(Heroicon::OutlinedChartBar);
    });

    it('has correct navigation group', function (): void {
        expect(ShippingDashboard::getNavigationGroup())->toBe('Shipping');
    });

    it('has correct navigation label', function (): void {
        expect(ShippingDashboard::getNavigationLabel())->toBe('Dashboard');
    });

    it('has navigation sort order of 0', function (): void {
        $reflection = new ReflectionProperty(ShippingDashboard::class, 'navigationSort');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(0);
    });

    it('has correct slug', function (): void {
        $reflection = new ReflectionProperty(ShippingDashboard::class, 'slug');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('shipping-dashboard');
    });
});

describe('ManifestPage', function (): void {
    it('has correct navigation icon', function (): void {
        $reflection = new ReflectionProperty(ManifestPage::class, 'navigationIcon');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(Heroicon::OutlinedDocumentText);
    });

    it('has correct navigation group', function (): void {
        expect(ManifestPage::getNavigationGroup())->toBe('Shipping');
    });

    it('has correct navigation label', function (): void {
        expect(ManifestPage::getNavigationLabel())->toBe('Manifests');
    });

    it('has navigation sort order of 5', function (): void {
        $reflection = new ReflectionProperty(ManifestPage::class, 'navigationSort');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(5);
    });

    it('has correct slug', function (): void {
        $reflection = new ReflectionProperty(ManifestPage::class, 'slug');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('shipping-manifests');
    });
});
