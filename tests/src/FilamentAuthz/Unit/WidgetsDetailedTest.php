<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Widgets\PermissionsDiffWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('PermissionStatsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PermissionStatsWidget;
        expect($widget)->toBeInstanceOf(PermissionStatsWidget::class);
    });

    it('extends StatsOverviewWidget', function (): void {
        expect(is_subclass_of(PermissionStatsWidget::class, StatsOverviewWidget::class))->toBeTrue();
    });

    it('has a sort property', function (): void {
        $reflection = new ReflectionClass(PermissionStatsWidget::class);
        $property = $reflection->getProperty('sort');
        expect($property->getValue(null))->toBe(1);
    });

    it('has getStats method', function (): void {
        expect(method_exists(PermissionStatsWidget::class, 'getStats'))->toBeTrue();
    });

    it('has helper counting methods', function (): void {
        $reflection = new ReflectionClass(PermissionStatsWidget::class);
        expect($reflection->hasMethod('countUsersWithRoles'))->toBeTrue()
            ->and($reflection->hasMethod('countUnassignedPermissions'))->toBeTrue();
    });

    it('generates stats array with 4 stats', function (): void {
        // Create test data
        Role::findOrCreate('test-stats-role', 'web');
        Permission::findOrCreate('test.stats.permission', 'web');

        $widget = new PermissionStatsWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $stats = $method->invoke($widget);

        expect($stats)->toBeArray()
            ->and(count($stats))->toBe(4);

        // Clean up
        Role::where('name', 'test-stats-role')->delete();
        Permission::where('name', 'test.stats.permission')->delete();
    });
});

describe('PermissionsDiffWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PermissionsDiffWidget;
        expect($widget)->toBeInstanceOf(PermissionsDiffWidget::class);
    });

    it('extends StatsOverviewWidget', function (): void {
        expect(is_subclass_of(PermissionsDiffWidget::class, StatsOverviewWidget::class))->toBeTrue();
    });

    it('has a heading property', function (): void {
        $reflection = new ReflectionClass(PermissionsDiffWidget::class);
        $property = $reflection->getProperty('heading');
        $widget = new PermissionsDiffWidget;
        expect($property->getValue($widget))->toBe('Permissions Overview');
    });

    it('has canView method', function (): void {
        expect(method_exists(PermissionsDiffWidget::class, 'canView'))->toBeTrue();
    });

    it('has getStats method', function (): void {
        expect(method_exists(PermissionsDiffWidget::class, 'getStats'))->toBeTrue();
    });

    it('generates stats array with 3 stats', function (): void {
        // Create test data
        Role::findOrCreate('test-diff-role', 'web');
        Permission::findOrCreate('test.diff.permission', 'web');

        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $stats = $method->invoke($widget);

        expect($stats)->toBeArray()
            ->and(count($stats))->toBe(3);

        // Clean up
        Role::where('name', 'test-diff-role')->delete();
        Permission::where('name', 'test.diff.permission')->delete();
    });
});

describe('RecentActivityWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new RecentActivityWidget;
        expect($widget)->toBeInstanceOf(RecentActivityWidget::class);
    });

    it('extends TableWidget', function (): void {
        expect(is_subclass_of(RecentActivityWidget::class, TableWidget::class))->toBeTrue();
    });

    it('has a sort property', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('sort');
        expect($property->getValue(null))->toBe(3);
    });

    it('has columnSpan property set to full', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('columnSpan');
        $widget = new RecentActivityWidget;
        expect($property->getValue($widget))->toBe('full');
    });

    it('has heading property', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('heading');
        expect($property->getValue(null))->toBe('Recent Permission Activity');
    });

    it('has table method', function (): void {
        expect(method_exists(RecentActivityWidget::class, 'table'))->toBeTrue();
    });
});
