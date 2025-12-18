<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\EditRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\ListRoles;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('RoleResource Metadata', function (): void {
    it('has correct model from config', function (): void {
        config(['permission.models.role' => Role::class]);
        expect(RoleResource::getModel())->toBe(Role::class);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Access Control']);
        expect(RoleResource::getNavigationGroup())->toBe('Access Control');
    });

    it('returns navigation icon from config', function (): void {
        config(['filament-authz.navigation.icons.roles' => 'heroicon-o-user-group']);
        expect(RoleResource::getNavigationIcon())->toBe('heroicon-o-user-group');
    });

    it('returns navigation sort from config', function (): void {
        config(['filament-authz.navigation.sort' => 20]);
        expect(RoleResource::getNavigationSort())->toBe(20);
    });

    it('has permissions relation manager', function (): void {
        $relations = RoleResource::getRelations();
        expect($relations)->toContain(PermissionsRelationManager::class);
    });
});

describe('RoleResource Pages', function (): void {
    it('has index page', function (): void {
        $pages = RoleResource::getPages();
        expect($pages)->toHaveKey('index')
            ->and($pages['index']->getPage())->toBe(ListRoles::class);
    });

    it('has create page', function (): void {
        $pages = RoleResource::getPages();
        expect($pages)->toHaveKey('create')
            ->and($pages['create']->getPage())->toBe(CreateRole::class);
    });

    it('has edit page', function (): void {
        $pages = RoleResource::getPages();
        expect($pages)->toHaveKey('edit')
            ->and($pages['edit']->getPage())->toBe(EditRole::class);
    });
});

describe('RoleResource shouldRegisterNavigation', function (): void {
    it('returns false when no user is logged in', function (): void {
        auth()->logout();
        expect(RoleResource::shouldRegisterNavigation())->toBeFalse();
    });
});

describe('RoleResource Form Schema', function (): void {
    it('returns form schema', function (): void {
        $schema = RoleResource::form(Schema::make(null));
        expect($schema)->toBeInstanceOf(Schema::class);
    });
});
