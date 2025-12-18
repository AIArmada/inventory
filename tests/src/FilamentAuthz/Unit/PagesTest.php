<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;

describe('AuditLogPage', function (): void {
    it('can be instantiated', function (): void {
        $page = new AuditLogPage;

        expect($page)->toBeInstanceOf(AuditLogPage::class);
    });

    it('has correct navigation label', function (): void {
        $reflection = new ReflectionClass(AuditLogPage::class);
        $property = $reflection->getProperty('navigationLabel');

        expect($property->getDefaultValue())->toBe('Audit Log');
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(AuditLogPage::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Permission Audit Log');
    });

    it('has correct view path', function (): void {
        $page = new AuditLogPage;
        $reflection = new ReflectionClass($page);
        $property = $reflection->getProperty('view');

        expect($property->getValue($page))->toBe('filament-authz::pages.audit-log');
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Administration']);

        expect(AuditLogPage::getNavigationGroup())->toBe('Administration');
    });

    it('provides event type options', function (): void {
        $page = new AuditLogPage;
        $options = $page->getEventTypeOptions();

        expect($options)->toBeArray()
            ->and($options)->not->toBeEmpty();
    });

    it('can clear filters', function (): void {
        $page = new AuditLogPage;
        $page->eventTypeFilter = 'test';
        $page->severityFilter = 'high';
        $page->logs = collect();

        $page->clearFilters();

        expect($page->eventTypeFilter)->toBeNull()
            ->and($page->severityFilter)->toBeNull();
    });
});

describe('PermissionMatrixPage', function (): void {
    it('can be instantiated', function (): void {
        $page = new PermissionMatrixPage;

        expect($page)->toBeInstanceOf(PermissionMatrixPage::class);
    });

    it('has correct navigation label', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('navigationLabel');

        expect($property->getDefaultValue())->toBe('Permission Matrix');
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Permission Matrix');
    });

    it('has correct view path', function (): void {
        $page = new PermissionMatrixPage;
        $reflection = new ReflectionClass($page);
        $property = $reflection->getProperty('view');

        expect($property->getValue($page))->toBe('filament-authz::pages.permission-matrix');
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Administration']);

        expect(PermissionMatrixPage::getNavigationGroup())->toBe('Administration');
    });

    it('returns null for selected role name when no role selected', function (): void {
        $page = new PermissionMatrixPage;
        $page->selectedRole = null;

        expect($page->getSelectedRoleName())->toBeNull();
    });

    it('toggles permission correctly', function (): void {
        $page = new PermissionMatrixPage;
        $page->permissions = ['123' => false];

        $page->togglePermission('123');

        expect($page->permissions['123'])->toBeTrue();

        $page->togglePermission('123');

        expect($page->permissions['123'])->toBeFalse();
    });

    it('returns empty matrix data when no permissions', function (): void {
        $page = new PermissionMatrixPage;
        $page->groupedPermissions = [];
        $page->permissions = [];

        $matrix = $page->getMatrixData();

        expect($matrix)->toBeArray();
    });
});

describe('RoleHierarchyPage', function (): void {
    it('can be instantiated', function (): void {
        $page = new RoleHierarchyPage;

        expect($page)->toBeInstanceOf(RoleHierarchyPage::class);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Administration']);

        expect(RoleHierarchyPage::getNavigationGroup())->toBe('Administration');
    });
});

describe('PermissionExplorer', function (): void {
    it('can be instantiated', function (): void {
        $page = new PermissionExplorer;

        expect($page)->toBeInstanceOf(PermissionExplorer::class);
    });
});
