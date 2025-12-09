<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\Discovery\ResourceTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\PageTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\WidgetTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;

test('discovered resource generates permission keys', function (): void {
    $resource = new DiscoveredResource(
        fqcn: 'App\\Filament\\Resources\\UserResource',
        model: 'App\\Models\\User',
        permissions: ['viewAny', 'view', 'create', 'update', 'delete'],
        metadata: [],
        panel: 'admin'
    );

    $keys = $resource->toPermissionKeys();

    expect($keys)->toContain('user.viewAny')
        ->toContain('user.view')
        ->toContain('user.create')
        ->toContain('user.update')
        ->toContain('user.delete');
});

test('discovered resource with custom separator', function (): void {
    $resource = new DiscoveredResource(
        fqcn: 'App\\Filament\\Resources\\UserResource',
        model: 'App\\Models\\User',
        permissions: ['viewAny'],
        metadata: [],
        panel: 'admin'
    );

    $keys = $resource->toPermissionKeys(':');

    expect($keys)->toContain('user:viewAny');
});

test('discovered resource converts to array', function (): void {
    $resource = new DiscoveredResource(
        fqcn: 'App\\Filament\\Resources\\UserResource',
        model: 'App\\Models\\User',
        permissions: ['viewAny'],
        metadata: ['hasRelations' => true],
        panel: 'admin',
        navigationGroup: 'Settings'
    );

    $array = $resource->toArray();

    expect($array)
        ->toHaveKey('fqcn', 'App\\Filament\\Resources\\UserResource')
        ->toHaveKey('model', 'App\\Models\\User')
        ->toHaveKey('panel', 'admin')
        ->toHaveKey('navigation_group', 'Settings')
        ->toHaveKey('permissions');
});

test('discovered page generates permission key', function (): void {
    $page = new DiscoveredPage(
        fqcn: 'App\\Filament\\Pages\\Settings',
        title: 'Settings',
        slug: 'settings',
        panel: 'admin'
    );

    expect($page->getPermissionKey())->toBe('page.settings');
});

test('discovered widget generates permission key', function (): void {
    $widget = new DiscoveredWidget(
        fqcn: 'App\\Filament\\Widgets\\StatsOverview',
        name: 'stats_overview',
        type: 'stats',
        panel: 'admin'
    );

    expect($widget->getPermissionKey())->toBe('widget.stats_overview');
});

test('entity discovery service can be instantiated', function (): void {
    $service = app(EntityDiscoveryService::class);

    expect($service)->toBeInstanceOf(EntityDiscoveryService::class);
});

test('entity discovery service discovers entities without errors', function (): void {
    $service = app(EntityDiscoveryService::class);

    // This should not throw any errors
    $result = $service->discoverAll();

    expect($result)->toHaveKeys(['resources', 'pages', 'widgets']);
});

test('entity discovery service can get discovered permissions', function (): void {
    $service = app(EntityDiscoveryService::class);

    $permissions = $service->getDiscoveredPermissions();

    expect($permissions)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('entity discovery service cache can be cleared', function (): void {
    $service = app(EntityDiscoveryService::class);

    // This should not throw
    $service->clearCache();

    expect(true)->toBeTrue();
});

test('entity discovery service cache can be warmed', function (): void {
    $service = app(EntityDiscoveryService::class);

    // This should not throw
    $service->warmCache();

    expect(true)->toBeTrue();
});
