<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

describe('Multi-Panel Support', function (): void {
    it('can be scoped to tenant', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->scopeToTenant();

        expect($plugin->isScopedToTenant())->toBeTrue();
    });

    it('can disable tenant scoping', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->scopeToTenant(false);

        expect($plugin->isScopedToTenant())->toBeFalse();
    });

    it('can set tenant ownership relationship', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->tenantOwnershipRelationshipName('team');

        expect($plugin->getTenantOwnershipRelationshipName())->toBe('team');
    });

    it('can use closure for tenant scoping', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->scopeToTenant(fn () => true);

        expect($plugin->isScopedToTenant())->toBeTrue();
    });

    it('can use closure for tenant relationship', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->tenantOwnershipRelationshipName(fn () => 'organization');

        expect($plugin->getTenantOwnershipRelationshipName())->toBe('organization');
    });

    it('returns null panel when not registered', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getPanel())->toBeNull();
    });

    it('has fluent API for tenant methods', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        $result = $plugin->scopeToTenant()
            ->tenantOwnershipRelationshipName('teams');

        expect($result)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });
});

describe('Plugin Configuration', function (): void {
    it('can enable role resource', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->roleResource();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable role resource', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->roleResource(false);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can set navigation group', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->navigationGroup('Settings');

        expect($plugin->getNavigationGroup())->toBe('Settings');
    });

    it('can set navigation icon', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->navigationIcon('heroicon-o-shield-check');

        expect($plugin->getNavigationIcon())->toBe('heroicon-o-shield-check');
    });

    it('can set navigation sort', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->navigationSort(10);

        expect($plugin->getNavigationSort())->toBe(10);
    });

    it('can set grid columns', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->gridColumns(3);

        expect($plugin->getGridColumns())->toBe(3);
    });

    it('can set checkbox list columns', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->checkboxListColumns(4);

        expect($plugin->getCheckboxListColumns())->toBe(4);
    });

    it('has correct plugin id', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getId())->toBe('aiarmada-filament-authz');
    });

    it('can be instantiated via make method', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can use fluent interface chaining', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->roleResource()
            ->permissionResource()
            ->navigationGroup('Access Control')
            ->navigationIcon('heroicon-o-lock-closed')
            ->navigationSort(5)
            ->gridColumns(3)
            ->checkboxListColumns(4)
            ->resourcesTab()
            ->pagesTab()
            ->widgetsTab()
            ->customPermissionsTab()
            ->scopeToTenant()
            ->tenantOwnershipRelationshipName('team');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        expect($plugin->getNavigationGroup())->toBe('Access Control');
        expect($plugin->getNavigationIcon())->toBe('heroicon-o-lock-closed');
        expect($plugin->getNavigationSort())->toBe(5);
        expect($plugin->getGridColumns())->toBe(3);
        expect($plugin->getCheckboxListColumns())->toBe(4);
        expect($plugin->isScopedToTenant())->toBeTrue();
        expect($plugin->getTenantOwnershipRelationshipName())->toBe('team');
    });
});
