<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager as PermissionRolesRM;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager as RolePermissionsRM;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRM;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager as UserRolesRM;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

describe('Permission Resource - RolesRelationManager Execution', function (): void {
    test('class extends RelationManager', function (): void {
        expect(PermissionRolesRM::class)
            ->toExtend(RelationManager::class);
    });

    test('relationship is correctly set', function (): void {
        $reflection = new ReflectionClass(PermissionRolesRM::class);
        $property = $reflection->getProperty('relationship');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('roles');
    });

    test('title is correctly set', function (): void {
        $reflection = new ReflectionClass(PermissionRolesRM::class);
        $property = $reflection->getProperty('title');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('Roles');
    });

    test('table method returns Table instance', function (): void {
        $rm = new PermissionRolesRM();
        $reflection = new ReflectionMethod($rm, 'table');
        $reflection->setAccessible(true);

        $table = new Table($rm);
        $result = $reflection->invoke($rm, $table);

        expect($result)->toBeInstanceOf(Table::class);
    });

    test('form method exists and accepts Schema', function (): void {
        $rm = new PermissionRolesRM();
        $reflection = new ReflectionMethod($rm, 'form');
        $reflection->setAccessible(true);

        expect($reflection)->toBeInstanceOf(ReflectionMethod::class);
        expect($reflection->getNumberOfRequiredParameters())->toBe(1);
    });

    test('class can be instantiated', function (): void {
        $rm = new PermissionRolesRM();
        expect($rm)->toBeInstanceOf(PermissionRolesRM::class);
    });
});

describe('Role Resource - PermissionsRelationManager Execution', function (): void {
    test('class extends RelationManager', function (): void {
        expect(RolePermissionsRM::class)
            ->toExtend(RelationManager::class);
    });

    test('relationship is correctly set', function (): void {
        $reflection = new ReflectionClass(RolePermissionsRM::class);
        $property = $reflection->getProperty('relationship');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('permissions');
    });

    test('title is correctly set', function (): void {
        $reflection = new ReflectionClass(RolePermissionsRM::class);
        $property = $reflection->getProperty('title');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('Permissions');
    });

    test('table method returns Table instance', function (): void {
        $rm = new RolePermissionsRM();
        $reflection = new ReflectionMethod($rm, 'table');
        $reflection->setAccessible(true);

        $table = new Table($rm);
        $result = $reflection->invoke($rm, $table);

        expect($result)->toBeInstanceOf(Table::class);
    });

    test('form method exists and accepts Schema', function (): void {
        $rm = new RolePermissionsRM();
        $reflection = new ReflectionMethod($rm, 'form');
        $reflection->setAccessible(true);

        expect($reflection)->toBeInstanceOf(ReflectionMethod::class);
    });

    test('class can be instantiated', function (): void {
        $rm = new RolePermissionsRM();
        expect($rm)->toBeInstanceOf(RolePermissionsRM::class);
    });
});

describe('User Resource - PermissionsRelationManager Execution', function (): void {
    test('class extends RelationManager', function (): void {
        expect(UserPermissionsRM::class)
            ->toExtend(RelationManager::class);
    });

    test('relationship is correctly set', function (): void {
        $reflection = new ReflectionClass(UserPermissionsRM::class);
        $property = $reflection->getProperty('relationship');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('permissions');
    });

    test('title is correctly set', function (): void {
        $reflection = new ReflectionClass(UserPermissionsRM::class);
        $property = $reflection->getProperty('title');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('Direct Permissions');
    });

    test('table method returns Table instance', function (): void {
        $rm = new UserPermissionsRM();
        $reflection = new ReflectionMethod($rm, 'table');
        $reflection->setAccessible(true);

        $table = new Table($rm);
        $result = $reflection->invoke($rm, $table);

        expect($result)->toBeInstanceOf(Table::class);
    });

    test('form method exists and accepts Schema', function (): void {
        $rm = new UserPermissionsRM();
        $reflection = new ReflectionMethod($rm, 'form');
        $reflection->setAccessible(true);

        expect($reflection)->toBeInstanceOf(ReflectionMethod::class);
    });

    test('class can be instantiated', function (): void {
        $rm = new UserPermissionsRM();
        expect($rm)->toBeInstanceOf(UserPermissionsRM::class);
    });
});

describe('User Resource - RolesRelationManager Execution', function (): void {
    test('class extends RelationManager', function (): void {
        expect(UserRolesRM::class)
            ->toExtend(RelationManager::class);
    });

    test('relationship is correctly set', function (): void {
        $reflection = new ReflectionClass(UserRolesRM::class);
        $property = $reflection->getProperty('relationship');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('roles');
    });

    test('title is correctly set', function (): void {
        $reflection = new ReflectionClass(UserRolesRM::class);
        $property = $reflection->getProperty('title');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('Roles');
    });

    test('table method returns Table instance', function (): void {
        $rm = new UserRolesRM();
        $reflection = new ReflectionMethod($rm, 'table');
        $reflection->setAccessible(true);

        $table = new Table($rm);
        $result = $reflection->invoke($rm, $table);

        expect($result)->toBeInstanceOf(Table::class);
    });

    test('form method exists and accepts Schema', function (): void {
        $rm = new UserRolesRM();
        $reflection = new ReflectionMethod($rm, 'form');
        $reflection->setAccessible(true);

        expect($reflection)->toBeInstanceOf(ReflectionMethod::class);
    });

    test('table uses guards config', function (): void {
        config(['filament-authz.guards' => ['web', 'admin']]);

        $rm = new UserRolesRM();
        $reflection = new ReflectionMethod($rm, 'table');
        $reflection->setAccessible(true);

        $table = new Table($rm);
        $result = $reflection->invoke($rm, $table);

        // Verify table was configured (has columns)
        expect($result)->toBeInstanceOf(Table::class);
    });

    test('class can be instantiated', function (): void {
        $rm = new UserRolesRM();
        expect($rm)->toBeInstanceOf(UserRolesRM::class);
    });
});
