<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasOwnerPermissions;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\EditDelegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ListDelegations;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ViewDelegation;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\EditPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ListPermissionRequests;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ViewPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\ListPermissions;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager as PermissionRolesRelationManager;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\EditRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\ListRoles;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager as RolePermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\EditUser;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\ListUsers;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager as UserRolesRelationManager;
use AIArmada\FilamentAuthz\Widgets\PermissionsDiffWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;

describe('HasOwnerPermissions Trait', function (): void {
    it('has required methods', function (): void {
        $traitMethods = get_class_methods(new class
        {
            use HasOwnerPermissions;

            public function getTable(): string
            {
                return 'test_table';
            }

            public function getAttribute(string $key): mixed
            {
                return null;
            }
        });

        expect($traitMethods)->toContain('canUserPerform')
            ->toContain('isOwnedBy')
            ->toContain('scopeOwnedBy')
            ->toContain('scopeViewableBy');
    });

    it('provides owner key name', function (): void {
        $model = new class
        {
            use HasOwnerPermissions;

            public function getTable(): string
            {
                return 'test_table';
            }

            public function getAttribute(string $key): mixed
            {
                return null;
            }

            public function testGetOwnerKeyName(): string
            {
                return $this->getOwnerKeyName();
            }
        };

        expect($model->testGetOwnerKeyName())->toBe('user_id');
    });

    it('generates correct permission names', function (): void {
        $model = new class
        {
            use HasOwnerPermissions;

            public function getTable(): string
            {
                return 'products';
            }

            public function getAttribute(string $key): mixed
            {
                return null;
            }

            public function testGetPermissionName(string $action): string
            {
                return $this->getPermissionName($action);
            }

            public function testGetOwnerPermissionName(string $action): string
            {
                return $this->getOwnerPermissionName($action);
            }
        };

        expect($model->testGetPermissionName('view'))->toBe('products.view')
            ->and($model->testGetOwnerPermissionName('edit'))->toBe('products.edit.own');
    });

    it('removes common prefixes from table name', function (): void {
        $model = new class
        {
            use HasOwnerPermissions;

            public function getTable(): string
            {
                return 'authz_permissions';
            }

            public function getAttribute(string $key): mixed
            {
                return null;
            }

            public function testGetResourceName(): string
            {
                return $this->getResourceName();
            }
        };

        expect($model->testGetResourceName())->toBe('permissions');
    });
});

describe('HasPanelAuthz Trait', function (): void {
    it('has required methods', function (): void {
        $traitMethods = get_class_methods(new class
        {
            use HasPanelAuthz;
        });

        expect($traitMethods)->toContain('canAccessPanel')
            ->toContain('getAccessiblePanels')
            ->toContain('hasAnyPanelAccess')
            ->toContain('getDefaultPanel');
    });

    it('has boot method', function (): void {
        expect(method_exists(
            new class
            {
                use HasPanelAuthz;
            },
            'bootHasPanelAuthz'
        ))->toBeTrue();
    });
});

describe('PermissionExplorer Page', function (): void {
    it('has correct class', function (): void {
        expect(class_exists(PermissionExplorer::class))->toBeTrue();
    });

    it('extends Page', function (): void {
        $reflection = new ReflectionClass(PermissionExplorer::class);
        expect($reflection->isSubclassOf(Filament\Pages\Page::class))->toBeTrue();
    });

    it('has correct static properties', function (): void {
        // Navigation group is set via config, could be null or a string
        $group = PermissionExplorer::getNavigationGroup();
        expect($group)->toBeIn([null, config('filament-authz.navigation.group')]);
    });

    it('has required methods', function (): void {
        expect(method_exists(PermissionExplorer::class, 'canAccess'))->toBeTrue()
            ->and(method_exists(PermissionExplorer::class, 'getPermissionsGrouped'))->toBeTrue()
            ->and(method_exists(PermissionExplorer::class, 'getRolesWithPermissionCounts'))->toBeTrue();
    });
});

describe('PermissionStatsWidget', function (): void {
    it('has correct class', function (): void {
        expect(class_exists(PermissionStatsWidget::class))->toBeTrue();
    });

    it('extends StatsOverviewWidget', function (): void {
        $reflection = new ReflectionClass(PermissionStatsWidget::class);
        expect($reflection->isSubclassOf(Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
    });

    it('has getStats method', function (): void {
        expect(method_exists(PermissionStatsWidget::class, 'getStats'))->toBeTrue();
    });
});

describe('PermissionsDiffWidget', function (): void {
    it('has correct class', function (): void {
        expect(class_exists(PermissionsDiffWidget::class))->toBeTrue();
    });

    it('extends StatsOverviewWidget', function (): void {
        $reflection = new ReflectionClass(PermissionsDiffWidget::class);
        expect($reflection->isSubclassOf(Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
    });

    it('has canView method', function (): void {
        expect(method_exists(PermissionsDiffWidget::class, 'canView'))->toBeTrue();
    });

    it('has getStats method', function (): void {
        expect(method_exists(PermissionsDiffWidget::class, 'getStats'))->toBeTrue();
    });
});

describe('RecentActivityWidget', function (): void {
    it('has correct class', function (): void {
        expect(class_exists(RecentActivityWidget::class))->toBeTrue();
    });

    it('extends TableWidget', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        expect($reflection->isSubclassOf(Filament\Widgets\TableWidget::class))->toBeTrue();
    });

    it('has table method', function (): void {
        expect(method_exists(RecentActivityWidget::class, 'table'))->toBeTrue();
    });
});

describe('DelegationResource Pages', function (): void {
    it('EditDelegation is properly configured', function (): void {
        expect(class_exists(EditDelegation::class))->toBeTrue();
        $reflection = new ReflectionClass(EditDelegation::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\EditRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(DelegationResource::class);
    });

    it('ListDelegations is properly configured', function (): void {
        expect(class_exists(ListDelegations::class))->toBeTrue();
        $reflection = new ReflectionClass(ListDelegations::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ListRecords::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(DelegationResource::class);
    });

    it('ViewDelegation is properly configured', function (): void {
        expect(class_exists(ViewDelegation::class))->toBeTrue();
        $reflection = new ReflectionClass(ViewDelegation::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ViewRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(DelegationResource::class);
    });
});

describe('PermissionRequestResource Pages', function (): void {
    it('EditPermissionRequest is properly configured', function (): void {
        expect(class_exists(EditPermissionRequest::class))->toBeTrue();
        $reflection = new ReflectionClass(EditPermissionRequest::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\EditRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(PermissionRequestResource::class);
    });

    it('ListPermissionRequests is properly configured', function (): void {
        expect(class_exists(ListPermissionRequests::class))->toBeTrue();
        $reflection = new ReflectionClass(ListPermissionRequests::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ListRecords::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(PermissionRequestResource::class);
    });

    it('ViewPermissionRequest is properly configured', function (): void {
        expect(class_exists(ViewPermissionRequest::class))->toBeTrue();
        $reflection = new ReflectionClass(ViewPermissionRequest::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ViewRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(PermissionRequestResource::class);
    });
});

describe('PermissionResource Pages', function (): void {
    it('CreatePermission is properly configured', function (): void {
        expect(class_exists(CreatePermission::class))->toBeTrue();
        $reflection = new ReflectionClass(CreatePermission::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\CreateRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(PermissionResource::class);
    });

    it('EditPermission is properly configured', function (): void {
        expect(class_exists(EditPermission::class))->toBeTrue();
        $reflection = new ReflectionClass(EditPermission::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\EditRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(PermissionResource::class);
    });

    it('ListPermissions is properly configured', function (): void {
        expect(class_exists(ListPermissions::class))->toBeTrue();
        $reflection = new ReflectionClass(ListPermissions::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ListRecords::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(PermissionResource::class);
    });
});

describe('RoleResource Pages', function (): void {
    it('CreateRole is properly configured', function (): void {
        expect(class_exists(CreateRole::class))->toBeTrue();
        $reflection = new ReflectionClass(CreateRole::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\CreateRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(RoleResource::class);
    });

    it('EditRole is properly configured', function (): void {
        expect(class_exists(EditRole::class))->toBeTrue();
        $reflection = new ReflectionClass(EditRole::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\EditRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(RoleResource::class);
    });

    it('ListRoles is properly configured', function (): void {
        expect(class_exists(ListRoles::class))->toBeTrue();
        $reflection = new ReflectionClass(ListRoles::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ListRecords::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(RoleResource::class);
    });
});

describe('UserResource Pages', function (): void {
    it('EditUser is properly configured', function (): void {
        expect(class_exists(EditUser::class))->toBeTrue();
        $reflection = new ReflectionClass(EditUser::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\EditRecord::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(UserResource::class);
    });

    it('ListUsers is properly configured', function (): void {
        expect(class_exists(ListUsers::class))->toBeTrue();
        $reflection = new ReflectionClass(ListUsers::class);
        expect($reflection->isSubclassOf(Filament\Resources\Pages\ListRecords::class))->toBeTrue();

        $resourceProperty = $reflection->getProperty('resource');
        expect($resourceProperty->getValue(null))->toBe(UserResource::class);
    });
});

describe('RoleResource RelationManagers', function (): void {
    it('PermissionsRelationManager is properly configured', function (): void {
        expect(class_exists(RolePermissionsRelationManager::class))->toBeTrue();
        $reflection = new ReflectionClass(RolePermissionsRelationManager::class);
        expect($reflection->isSubclassOf(Filament\Resources\RelationManagers\RelationManager::class))->toBeTrue();

        $relationshipProperty = $reflection->getProperty('relationship');
        expect($relationshipProperty->getValue(null))->toBe('permissions');

        $titleProperty = $reflection->getProperty('title');
        expect($titleProperty->getValue(null))->toBe('Permissions');
    });

    it('has table and form methods', function (): void {
        expect(method_exists(RolePermissionsRelationManager::class, 'table'))->toBeTrue()
            ->and(method_exists(RolePermissionsRelationManager::class, 'form'))->toBeTrue();
    });
});

describe('UserResource RelationManagers', function (): void {
    it('PermissionsRelationManager is properly configured', function (): void {
        expect(class_exists(UserPermissionsRelationManager::class))->toBeTrue();
        $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
        expect($reflection->isSubclassOf(Filament\Resources\RelationManagers\RelationManager::class))->toBeTrue();

        $relationshipProperty = $reflection->getProperty('relationship');
        expect($relationshipProperty->getValue(null))->toBe('permissions');

        $titleProperty = $reflection->getProperty('title');
        expect($titleProperty->getValue(null))->toBe('Direct Permissions');
    });

    it('RolesRelationManager is properly configured', function (): void {
        expect(class_exists(UserRolesRelationManager::class))->toBeTrue();
        $reflection = new ReflectionClass(UserRolesRelationManager::class);
        expect($reflection->isSubclassOf(Filament\Resources\RelationManagers\RelationManager::class))->toBeTrue();

        $relationshipProperty = $reflection->getProperty('relationship');
        expect($relationshipProperty->getValue(null))->toBe('roles');

        $titleProperty = $reflection->getProperty('title');
        expect($titleProperty->getValue(null))->toBe('Roles');
    });

    it('UserRelationManagers have table and form methods', function (): void {
        expect(method_exists(UserPermissionsRelationManager::class, 'table'))->toBeTrue()
            ->and(method_exists(UserPermissionsRelationManager::class, 'form'))->toBeTrue()
            ->and(method_exists(UserRolesRelationManager::class, 'table'))->toBeTrue()
            ->and(method_exists(UserRolesRelationManager::class, 'form'))->toBeTrue();
    });
});

describe('PermissionResource RelationManagers', function (): void {
    it('RolesRelationManager is properly configured', function (): void {
        expect(class_exists(PermissionRolesRelationManager::class))->toBeTrue();
        $reflection = new ReflectionClass(PermissionRolesRelationManager::class);
        expect($reflection->isSubclassOf(Filament\Resources\RelationManagers\RelationManager::class))->toBeTrue();

        $relationshipProperty = $reflection->getProperty('relationship');
        expect($relationshipProperty->getValue(null))->toBe('roles');

        $titleProperty = $reflection->getProperty('title');
        expect($titleProperty->getValue(null))->toBe('Roles');
    });

    it('has table and form methods', function (): void {
        expect(method_exists(PermissionRolesRelationManager::class, 'table'))->toBeTrue()
            ->and(method_exists(PermissionRolesRelationManager::class, 'form'))->toBeTrue();
    });
});
