<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\ListPermissions;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('PermissionResource Metadata', function (): void {
    it('has correct model from config', function (): void {
        config(['permission.models.permission' => Permission::class]);
        expect(PermissionResource::getModel())->toBe(Permission::class);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Access Control']);
        expect(PermissionResource::getNavigationGroup())->toBe('Access Control');
    });

    it('returns navigation icon from config', function (): void {
        config(['filament-authz.navigation.icons.permissions' => 'heroicon-o-key']);
        expect(PermissionResource::getNavigationIcon())->toBe('heroicon-o-key');
    });

    it('returns navigation sort from config', function (): void {
        config(['filament-authz.navigation.sort' => 25]);
        expect(PermissionResource::getNavigationSort())->toBe(25);
    });

    it('has roles relation manager', function (): void {
        $relations = PermissionResource::getRelations();
        expect($relations)->toContain(RolesRelationManager::class);
    });
});

describe('PermissionResource Pages', function (): void {
    it('has index page', function (): void {
        $pages = PermissionResource::getPages();
        expect($pages)->toHaveKey('index')
            ->and($pages['index']->getPage())->toBe(ListPermissions::class);
    });

    it('has create page', function (): void {
        $pages = PermissionResource::getPages();
        expect($pages)->toHaveKey('create')
            ->and($pages['create']->getPage())->toBe(CreatePermission::class);
    });

    it('has edit page', function (): void {
        $pages = PermissionResource::getPages();
        expect($pages)->toHaveKey('edit')
            ->and($pages['edit']->getPage())->toBe(EditPermission::class);
    });
});

describe('PermissionResource shouldRegisterNavigation', function (): void {
    it('returns false when no user is logged in', function (): void {
        auth()->logout();
        expect(PermissionResource::shouldRegisterNavigation())->toBeFalse();
    });
});

describe('PermissionResource Form Schema', function (): void {
    it('returns form schema', function (): void {
        $schema = PermissionResource::form(Schema::make(null));
        expect($schema)->toBeInstanceOf(Schema::class);
    });
});
