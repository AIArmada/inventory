<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\EditDelegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ListDelegations;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ViewDelegation;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\EditPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ListPermissionRequests;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ViewPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\ListPermissions;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager as PermissionRolesRelationManager;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\EditRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\ListRoles;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager as RolePermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\EditUser;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\ListUsers;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager as UserRolesRelationManager;

describe('EditDelegation Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(EditDelegation::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(EditDelegation::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new EditDelegation;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ListDelegations Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ListDelegations::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ListDelegations::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ListDelegations;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ViewDelegation Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ViewDelegation::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ViewDelegation::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ViewDelegation;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('EditPermissionRequest Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(EditPermissionRequest::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(EditPermissionRequest::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new EditPermissionRequest;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ListPermissionRequests Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ListPermissionRequests::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ListPermissionRequests::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ListPermissionRequests;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ViewPermissionRequest Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ViewPermissionRequest::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ViewPermissionRequest::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ViewPermissionRequest;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('CreatePermission Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(CreatePermission::class))->toBeTrue();
    });

    it('has afterCreate method', function (): void {
        expect(method_exists(CreatePermission::class, 'afterCreate'))->toBeTrue();
    });
});

describe('EditPermission Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(EditPermission::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(EditPermission::class, 'getHeaderActions'))->toBeTrue();
    });

    it('has afterSave method', function (): void {
        expect(method_exists(EditPermission::class, 'afterSave'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new EditPermission;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ListPermissions Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ListPermissions::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ListPermissions::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ListPermissions;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('CreateRole Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(CreateRole::class))->toBeTrue();
    });

    it('has permissionIds property', function (): void {
        $reflection = new ReflectionClass(CreateRole::class);
        expect($reflection->hasProperty('permissionIds'))->toBeTrue();
    });

    it('has mutateFormDataBeforeCreate method', function (): void {
        expect(method_exists(CreateRole::class, 'mutateFormDataBeforeCreate'))->toBeTrue();
    });

    it('has afterCreate method', function (): void {
        expect(method_exists(CreateRole::class, 'afterCreate'))->toBeTrue();
    });
});

describe('EditRole Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(EditRole::class))->toBeTrue();
    });

    it('has permissionIds property', function (): void {
        $reflection = new ReflectionClass(EditRole::class);
        expect($reflection->hasProperty('permissionIds'))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(EditRole::class, 'getHeaderActions'))->toBeTrue();
    });

    it('has mutateFormDataBeforeSave method', function (): void {
        expect(method_exists(EditRole::class, 'mutateFormDataBeforeSave'))->toBeTrue();
    });

    it('has afterSave method', function (): void {
        expect(method_exists(EditRole::class, 'afterSave'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new EditRole;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ListRoles Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ListRoles::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ListRoles::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ListRoles;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('EditUser Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(EditUser::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(EditUser::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new EditUser;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('ListUsers Page', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(ListUsers::class))->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        expect(method_exists(ListUsers::class, 'getHeaderActions'))->toBeTrue();
    });

    it('returns array from getHeaderActions', function (): void {
        $page = new ListUsers;
        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $actions = $method->invoke($page);

        expect($actions)->toBeArray();
    });
});

describe('RolePermissionsRelationManager', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(RolePermissionsRelationManager::class))->toBeTrue();
    });

    it('has relationship property set to permissions', function (): void {
        $reflection = new ReflectionClass(RolePermissionsRelationManager::class);
        $property = $reflection->getProperty('relationship');
        expect($property->getValue(null))->toBe('permissions');
    });

    it('has title property set to Permissions', function (): void {
        $reflection = new ReflectionClass(RolePermissionsRelationManager::class);
        $property = $reflection->getProperty('title');
        expect($property->getValue(null))->toBe('Permissions');
    });

    it('has table method', function (): void {
        expect(method_exists(RolePermissionsRelationManager::class, 'table'))->toBeTrue();
    });

    it('has form method', function (): void {
        expect(method_exists(RolePermissionsRelationManager::class, 'form'))->toBeTrue();
    });
});

describe('UserPermissionsRelationManager', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(UserPermissionsRelationManager::class))->toBeTrue();
    });

    it('has relationship property set to permissions', function (): void {
        $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
        $property = $reflection->getProperty('relationship');
        expect($property->getValue(null))->toBe('permissions');
    });

    it('has title property set to Direct Permissions', function (): void {
        $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
        $property = $reflection->getProperty('title');
        expect($property->getValue(null))->toBe('Direct Permissions');
    });

    it('has table method', function (): void {
        expect(method_exists(UserPermissionsRelationManager::class, 'table'))->toBeTrue();
    });

    it('has form method', function (): void {
        expect(method_exists(UserPermissionsRelationManager::class, 'form'))->toBeTrue();
    });
});

describe('UserRolesRelationManager', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(UserRolesRelationManager::class))->toBeTrue();
    });

    it('has relationship property set to roles', function (): void {
        $reflection = new ReflectionClass(UserRolesRelationManager::class);
        $property = $reflection->getProperty('relationship');
        expect($property->getValue(null))->toBe('roles');
    });

    it('has title property set to Roles', function (): void {
        $reflection = new ReflectionClass(UserRolesRelationManager::class);
        $property = $reflection->getProperty('title');
        expect($property->getValue(null))->toBe('Roles');
    });

    it('has table method', function (): void {
        expect(method_exists(UserRolesRelationManager::class, 'table'))->toBeTrue();
    });

    it('has form method', function (): void {
        expect(method_exists(UserRolesRelationManager::class, 'form'))->toBeTrue();
    });
});

describe('PermissionRolesRelationManager', function (): void {
    it('exists as a class', function (): void {
        expect(class_exists(PermissionRolesRelationManager::class))->toBeTrue();
    });

    it('has relationship property set to roles', function (): void {
        $reflection = new ReflectionClass(PermissionRolesRelationManager::class);
        $property = $reflection->getProperty('relationship');
        expect($property->getValue(null))->toBe('roles');
    });

    it('has title property set to Roles', function (): void {
        $reflection = new ReflectionClass(PermissionRolesRelationManager::class);
        $property = $reflection->getProperty('title');
        expect($property->getValue(null))->toBe('Roles');
    });

    it('has table method', function (): void {
        expect(method_exists(PermissionRolesRelationManager::class, 'table'))->toBeTrue();
    });

    it('has form method', function (): void {
        expect(method_exists(PermissionRolesRelationManager::class, 'form'))->toBeTrue();
    });
});
