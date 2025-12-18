<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Support\DefaultAbilityToPermissionMapper;
use AIArmada\FilamentAuthz\Support\ResourcePermissionDiscovery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('RoleResource', function (): void {
    it('has correct model', function (): void {
        $model = RoleResource::getModel();

        expect($model)->toBe(Role::class);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);

        expect(RoleResource::getNavigationGroup())->toBe('Authorization');
    });

    it('returns navigation icon from config', function (): void {
        config(['filament-authz.navigation.icons.roles' => 'heroicon-o-shield-check']);

        expect(RoleResource::getNavigationIcon())->toBe('heroicon-o-shield-check');
    });

    it('returns navigation sort from config', function (): void {
        config(['filament-authz.navigation.sort' => 100]);

        expect(RoleResource::getNavigationSort())->toBe(100);
    });

    it('returns pages array', function (): void {
        $pages = RoleResource::getPages();

        expect($pages)->toBeArray()
            ->and($pages)->toHaveKey('index')
            ->and($pages)->toHaveKey('create')
            ->and($pages)->toHaveKey('edit');
    });

    it('returns relations array', function (): void {
        $relations = RoleResource::getRelations();

        expect($relations)->toBeArray();
    });

    it('shouldRegisterNavigation returns false when no user', function (): void {
        auth()->logout();
        expect(RoleResource::shouldRegisterNavigation())->toBeFalse();
    });
});

describe('PermissionResource', function (): void {
    it('has correct model', function (): void {
        $model = PermissionResource::getModel();

        expect($model)->toBe(Permission::class);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);

        expect(PermissionResource::getNavigationGroup())->toBe('Authorization');
    });

    it('returns navigation icon from config', function (): void {
        config(['filament-authz.navigation.icons.permissions' => 'heroicon-o-key']);

        expect(PermissionResource::getNavigationIcon())->toBe('heroicon-o-key');
    });

    it('returns navigation sort from config', function (): void {
        config(['filament-authz.navigation.sort' => 100]);

        expect(PermissionResource::getNavigationSort())->toBe(100);
    });

    it('returns pages array', function (): void {
        $pages = PermissionResource::getPages();

        expect($pages)->toBeArray()
            ->and($pages)->toHaveKey('index')
            ->and($pages)->toHaveKey('create')
            ->and($pages)->toHaveKey('edit');
    });

    it('returns relations array', function (): void {
        $relations = PermissionResource::getRelations();

        expect($relations)->toBeArray();
    });
});

describe('UserResource', function (): void {
    it('returns pages array', function (): void {
        $pages = UserResource::getPages();

        expect($pages)->toBeArray()
            ->and($pages)->toHaveKey('index')
            ->and($pages)->toHaveKey('create')
            ->and($pages)->toHaveKey('edit');
    });

    it('returns relations array', function (): void {
        $relations = UserResource::getRelations();

        expect($relations)->toBeArray();
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);

        expect(UserResource::getNavigationGroup())->toBe('Authorization');
    });
});

describe('DelegationResource', function (): void {
    it('returns pages array', function (): void {
        $pages = DelegationResource::getPages();

        expect($pages)->toBeArray()
            ->and($pages)->toHaveKey('index')
            ->and($pages)->toHaveKey('create')
            ->and($pages)->toHaveKey('view')
            ->and($pages)->toHaveKey('edit');
    });

    it('has model name', function (): void {
        $model = DelegationResource::getModel();

        expect($model)->toBeString();
    });

    it('has navigation icon', function (): void {
        expect(DelegationResource::getNavigationIcon())->toBe('heroicon-o-arrows-right-left');
    });

    it('has navigation label', function (): void {
        expect(DelegationResource::getNavigationLabel())->toBe('Delegations');
    });

    it('has navigation group', function (): void {
        expect(DelegationResource::getNavigationGroup())->toBe('Authorization');
    });

    it('has navigation sort order', function (): void {
        expect(DelegationResource::getNavigationSort())->toBe(45);
    });

    it('has empty relations', function (): void {
        expect(DelegationResource::getRelations())->toBe([]);
    });

    it('returns navigation badge color', function (): void {
        expect(DelegationResource::getNavigationBadgeColor())->toBe('info');
    });

    it('canAccess returns false when delegation disabled', function (): void {
        config(['filament-authz.enterprise.delegation.enabled' => false]);
        expect(DelegationResource::canAccess())->toBeFalse();
    });

    it('canAccess returns true when delegation enabled', function (): void {
        config(['filament-authz.enterprise.delegation.enabled' => true]);
        expect(DelegationResource::canAccess())->toBeTrue();
    });

    it('returns record title attribute', function (): void {
        expect(DelegationResource::getRecordTitleAttribute())->toBe('id');
    });
});

describe('PermissionRequestResource', function (): void {
    it('returns pages array', function (): void {
        $pages = PermissionRequestResource::getPages();

        expect($pages)->toBeArray()
            ->and($pages)->toHaveKey('index')
            ->and($pages)->toHaveKey('create')
            ->and($pages)->toHaveKey('view')
            ->and($pages)->toHaveKey('edit');
    });

    it('has model name', function (): void {
        $model = PermissionRequestResource::getModel();

        expect($model)->toBeString();
    });

    it('has navigation icon', function (): void {
        expect(PermissionRequestResource::getNavigationIcon())->toBe('heroicon-o-clipboard-document-check');
    });

    it('has navigation label', function (): void {
        expect(PermissionRequestResource::getNavigationLabel())->toBe('Approval Requests');
    });

    it('has navigation group', function (): void {
        expect(PermissionRequestResource::getNavigationGroup())->toBe('Authorization');
    });

    it('has navigation sort order', function (): void {
        expect(PermissionRequestResource::getNavigationSort())->toBe(40);
    });

    it('has empty relations', function (): void {
        expect(PermissionRequestResource::getRelations())->toBe([]);
    });

    it('returns navigation badge color', function (): void {
        expect(PermissionRequestResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('canAccess returns false when approvals disabled', function (): void {
        config(['filament-authz.enterprise.approvals.enabled' => false]);
        expect(PermissionRequestResource::canAccess())->toBeFalse();
    });

    it('canAccess returns true when approvals enabled', function (): void {
        config(['filament-authz.enterprise.approvals.enabled' => true]);
        expect(PermissionRequestResource::canAccess())->toBeTrue();
    });

    it('returns record title attribute', function (): void {
        expect(PermissionRequestResource::getRecordTitleAttribute())->toBe('id');
    });
});

describe('DefaultAbilityToPermissionMapper', function (): void {
    it('maps model class and ability to permission key', function (): void {
        $mapper = new DefaultAbilityToPermissionMapper;

        $result = $mapper('App\\Models\\User', 'viewAny');

        expect($result)->toBe('user.viewAny');
    });

    it('handles different model classes', function (): void {
        $mapper = new DefaultAbilityToPermissionMapper;

        expect($mapper('App\\Models\\Post', 'create'))->toBe('post.create')
            ->and($mapper('App\\Models\\BlogCategory', 'update'))->toBe('blogcategory.update')
            ->and($mapper('App\\Models\\OrderItem', 'delete'))->toBe('orderitem.delete');
    });

    it('lowercases model name', function (): void {
        $mapper = new DefaultAbilityToPermissionMapper;

        $result = $mapper('App\\Models\\USER', 'view');

        expect($result)->toBe('user.view');
    });
});

describe('ResourcePermissionDiscovery', function (): void {
    beforeEach(function (): void {
        $this->registry = Mockery::mock(PermissionRegistry::class);
        $this->discovery = new ResourcePermissionDiscovery($this->registry);
    });

    it('can be instantiated', function (): void {
        expect($this->discovery)->toBeInstanceOf(ResourcePermissionDiscovery::class);
    });
});
