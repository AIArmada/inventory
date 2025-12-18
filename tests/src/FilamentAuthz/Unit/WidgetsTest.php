<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Widgets\ImpersonationBannerWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionsDiffWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;
use Illuminate\Support\Facades\Auth;

describe('PermissionStatsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PermissionStatsWidget;

        expect($widget)->toBeInstanceOf(PermissionStatsWidget::class);
    });

    it('has sort order set', function (): void {
        $reflection = new ReflectionClass(PermissionStatsWidget::class);
        $property = $reflection->getProperty('sort');

        expect($property->getDefaultValue())->toBe(1);
    });
});

describe('RoleHierarchyWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new RoleHierarchyWidget;

        expect($widget)->toBeInstanceOf(RoleHierarchyWidget::class);
    });

    it('has correct view path', function (): void {
        $widget = new RoleHierarchyWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('view');

        expect($property->getValue($widget))->toBe('filament-authz::widgets.role-hierarchy');
    });

    it('has full column span', function (): void {
        $widget = new RoleHierarchyWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('columnSpan');

        expect($property->getValue($widget))->toBe('full');
    });

    it('returns hierarchy as array', function (): void {
        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBeArray();
    });
});

describe('ImpersonationBannerWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new ImpersonationBannerWidget;

        expect($widget)->toBeInstanceOf(ImpersonationBannerWidget::class);
    });

    it('has correct view path', function (): void {
        $widget = new ImpersonationBannerWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('view');

        expect($property->getValue($widget))->toBe('filament-authz::widgets.impersonation-banner');
    });

    it('returns false for canView when no user logged in', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        expect(ImpersonationBannerWidget::canView())->toBeFalse();
    });

    it('returns None when no user for current role context', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $widget = new ImpersonationBannerWidget;
        $context = $widget->getCurrentRoleContext();

        expect($context)->toBe('None');
    });
});

describe('RecentActivityWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new RecentActivityWidget;

        expect($widget)->toBeInstanceOf(RecentActivityWidget::class);
    });

    it('has correct heading', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('heading');

        expect($property->getDefaultValue())->toBe('Recent Permission Activity');
    });

    it('has full column span', function (): void {
        $widget = new RecentActivityWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('columnSpan');

        expect($property->getValue($widget))->toBe('full');
    });

    it('has sort order of 3', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('sort');

        expect($property->getDefaultValue())->toBe(3);
    });
});

describe('PermissionsDiffWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PermissionsDiffWidget;

        expect($widget)->toBeInstanceOf(PermissionsDiffWidget::class);
    });
});
