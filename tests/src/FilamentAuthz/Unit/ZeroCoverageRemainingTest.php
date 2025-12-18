<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\RoleHierarchyCommand;
use AIArmada\FilamentAuthz\Console\RoleTemplateCommand;
use AIArmada\FilamentAuthz\Console\SetupCommand;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager as PermissionRolesRelationManager;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager as RolePermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager as UserRolesRelationManager;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use Filament\Tables\Table;

describe('Console Commands Remaining at 0% Coverage', function (): void {
    describe('RoleHierarchyCommand', function (): void {
        it('can be instantiated', function (): void {
            $command = new RoleHierarchyCommand;

            expect($command)->toBeInstanceOf(RoleHierarchyCommand::class);
        });

        it('has correct signature', function (): void {
            $command = new RoleHierarchyCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('authz:roles-hierarchy');
        });

        it('has correct description', function (): void {
            $command = new RoleHierarchyCommand;
            $reflection = new ReflectionProperty($command, 'description');
            $description = $reflection->getValue($command);

            expect($description)->toContain('role hierarchy');
        });

        it('has handle method', function (): void {
            expect(method_exists(RoleHierarchyCommand::class, 'handle'))->toBeTrue();
        });

        it('handle method requires RoleInheritanceService', function (): void {
            $reflection = new ReflectionMethod(RoleHierarchyCommand::class, 'handle');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(RoleInheritanceService::class);
        });

        it('has listRoles protected method', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);

            expect($reflection->hasMethod('listRoles'))->toBeTrue();
            expect($reflection->getMethod('listRoles')->isProtected())->toBeTrue();
        });

        it('has showTree protected method', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);

            expect($reflection->hasMethod('showTree'))->toBeTrue();
        });

        it('has setParent protected method', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);

            expect($reflection->hasMethod('setParent'))->toBeTrue();
        });

        it('has detachFromParent protected method', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);

            expect($reflection->hasMethod('detachFromParent'))->toBeTrue();
        });

        it('has searchRole protected method', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);

            expect($reflection->hasMethod('searchRole'))->toBeTrue();
        });

        it('supports list action option', function (): void {
            $command = new RoleHierarchyCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('{action?');
        });

        it('supports role option', function (): void {
            $command = new RoleHierarchyCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--role=');
        });

        it('supports parent option', function (): void {
            $command = new RoleHierarchyCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--parent=');
        });
    });

    describe('RoleTemplateCommand', function (): void {
        it('can be instantiated', function (): void {
            $command = new RoleTemplateCommand;

            expect($command)->toBeInstanceOf(RoleTemplateCommand::class);
        });

        it('has correct signature', function (): void {
            $command = new RoleTemplateCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('authz:templates');
        });

        it('has correct description', function (): void {
            $command = new RoleTemplateCommand;
            $reflection = new ReflectionProperty($command, 'description');
            $description = $reflection->getValue($command);

            expect($description)->toContain('role templates');
        });

        it('has handle method', function (): void {
            expect(method_exists(RoleTemplateCommand::class, 'handle'))->toBeTrue();
        });

        it('handle method requires RoleTemplateService', function (): void {
            $reflection = new ReflectionMethod(RoleTemplateCommand::class, 'handle');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(RoleTemplateService::class);
        });

        it('has listTemplates protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('listTemplates'))->toBeTrue();
        });

        it('has createTemplate protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('createTemplate'))->toBeTrue();
        });

        it('has createRoleFromTemplate protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('createRoleFromTemplate'))->toBeTrue();
        });

        it('has syncRole protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('syncRole'))->toBeTrue();
        });

        it('has syncAllRoles protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('syncAllRoles'))->toBeTrue();
        });

        it('has deleteTemplate protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('deleteTemplate'))->toBeTrue();
        });

        it('has searchTemplate protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('searchTemplate'))->toBeTrue();
        });

        it('has getPermissionOptions protected method', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);

            expect($reflection->hasMethod('getPermissionOptions'))->toBeTrue();
        });

        it('supports template option', function (): void {
            $command = new RoleTemplateCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--template=');
        });

        it('supports role option', function (): void {
            $command = new RoleTemplateCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--role=');
        });
    });

    describe('SetupCommand', function (): void {
        it('can be instantiated', function (): void {
            $command = new SetupCommand;

            expect($command)->toBeInstanceOf(SetupCommand::class);
        });

        it('has correct signature', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('authz:setup');
        });

        it('has correct description', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'description');
            $description = $reflection->getValue($command);

            expect($description)->toContain('setup wizard');
        });

        it('has state property', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasProperty('state'))->toBeTrue();
        });

        it('state is protected array', function (): void {
            $reflection = new ReflectionProperty(SetupCommand::class, 'state');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('has handle method', function (): void {
            expect(method_exists(SetupCommand::class, 'handle'))->toBeTrue();
        });

        it('has isProhibited protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('isProhibited'))->toBeTrue();
        });

        it('has welcome protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('welcome'))->toBeTrue();
        });

        it('has detectEnvironment protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('detectEnvironment'))->toBeTrue();
        });

        it('has configurePackage protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('configurePackage'))->toBeTrue();
        });

        it('has setupDatabase protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('setupDatabase'))->toBeTrue();
        });

        it('has setupRoles protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('setupRoles'))->toBeTrue();
        });

        it('has setupPermissions protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('setupPermissions'))->toBeTrue();
        });

        it('has setupPolicies protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('setupPolicies'))->toBeTrue();
        });

        it('has setupSuperAdmin protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('setupSuperAdmin'))->toBeTrue();
        });

        it('has verify protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('verify'))->toBeTrue();
        });

        it('has showCompletion protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('showCompletion'))->toBeTrue();
        });

        it('has displayDetection protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('displayDetection'))->toBeTrue();
        });

        it('has publishConfig protected method', function (): void {
            $reflection = new ReflectionClass(SetupCommand::class);

            expect($reflection->hasMethod('publishConfig'))->toBeTrue();
        });

        it('supports fresh option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--fresh');
        });

        it('supports force option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--force');
        });

        it('supports minimal option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--minimal');
        });

        it('supports tenant option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--tenant=');
        });

        it('supports panel option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--panel=');
        });

        it('supports skip-policies option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--skip-policies');
        });

        it('supports skip-permissions option', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionProperty($command, 'signature');
            $signature = $reflection->getValue($command);

            expect($signature)->toContain('--skip-permissions');
        });
    });
});

describe('Resource Create Pages at 0% Coverage', function (): void {
    describe('CreatePermission Page', function (): void {
        it('can be instantiated', function (): void {
            $page = new CreatePermission;

            expect($page)->toBeInstanceOf(CreatePermission::class);
        });

        it('has getResource method', function (): void {
            expect(method_exists(CreatePermission::class, 'getResource'))->toBeTrue();
        });

        it('extends correct base class', function (): void {
            $reflection = new ReflectionClass(CreatePermission::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Resources\\Pages\\CreateRecord');
        });
    });

    describe('CreateRole Page', function (): void {
        it('can be instantiated', function (): void {
            $page = new CreateRole;

            expect($page)->toBeInstanceOf(CreateRole::class);
        });

        it('has getResource method', function (): void {
            expect(method_exists(CreateRole::class, 'getResource'))->toBeTrue();
        });

        it('extends correct base class', function (): void {
            $reflection = new ReflectionClass(CreateRole::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Resources\\Pages\\CreateRecord');
        });
    });
});

describe('Relation Managers at 0% Coverage', function (): void {
    describe('PermissionResource RolesRelationManager', function (): void {
        it('can be instantiated', function (): void {
            $manager = new PermissionRolesRelationManager;

            expect($manager)->toBeInstanceOf(PermissionRolesRelationManager::class);
        });

        it('has correct relationship name', function (): void {
            expect(PermissionRolesRelationManager::getRelationshipName())->toBe('roles');
        });

        it('has form method', function (): void {
            expect(method_exists(PermissionRolesRelationManager::class, 'form'))->toBeTrue();
        });

        it('has table method', function (): void {
            expect(method_exists(PermissionRolesRelationManager::class, 'table'))->toBeTrue();
        });

        it('extends RelationManager', function (): void {
            $reflection = new ReflectionClass(PermissionRolesRelationManager::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Resources\\RelationManagers\\RelationManager');
        });
    });

    describe('RoleResource PermissionsRelationManager', function (): void {
        it('can be instantiated', function (): void {
            $manager = new RolePermissionsRelationManager;

            expect($manager)->toBeInstanceOf(RolePermissionsRelationManager::class);
        });

        it('has correct relationship name', function (): void {
            expect(RolePermissionsRelationManager::getRelationshipName())->toBe('permissions');
        });

        it('has form method', function (): void {
            expect(method_exists(RolePermissionsRelationManager::class, 'form'))->toBeTrue();
        });

        it('has table method', function (): void {
            expect(method_exists(RolePermissionsRelationManager::class, 'table'))->toBeTrue();
        });

        it('extends RelationManager', function (): void {
            $reflection = new ReflectionClass(RolePermissionsRelationManager::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Resources\\RelationManagers\\RelationManager');
        });
    });

    describe('UserResource PermissionsRelationManager', function (): void {
        it('can be instantiated', function (): void {
            $manager = new UserPermissionsRelationManager;

            expect($manager)->toBeInstanceOf(UserPermissionsRelationManager::class);
        });

        it('has correct relationship name', function (): void {
            expect(UserPermissionsRelationManager::getRelationshipName())->toBe('permissions');
        });

        it('has form method', function (): void {
            expect(method_exists(UserPermissionsRelationManager::class, 'form'))->toBeTrue();
        });

        it('has table method', function (): void {
            expect(method_exists(UserPermissionsRelationManager::class, 'table'))->toBeTrue();
        });

        it('extends RelationManager', function (): void {
            $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Resources\\RelationManagers\\RelationManager');
        });
    });

    describe('UserResource RolesRelationManager', function (): void {
        it('can be instantiated', function (): void {
            $manager = new UserRolesRelationManager;

            expect($manager)->toBeInstanceOf(UserRolesRelationManager::class);
        });

        it('has correct relationship name', function (): void {
            expect(UserRolesRelationManager::getRelationshipName())->toBe('roles');
        });

        it('has form method', function (): void {
            expect(method_exists(UserRolesRelationManager::class, 'form'))->toBeTrue();
        });

        it('has table method', function (): void {
            expect(method_exists(UserRolesRelationManager::class, 'table'))->toBeTrue();
        });

        it('extends RelationManager', function (): void {
            $reflection = new ReflectionClass(UserRolesRelationManager::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Resources\\RelationManagers\\RelationManager');
        });
    });
});

describe('Widgets at 0% Coverage', function (): void {
    describe('RecentActivityWidget', function (): void {
        it('can be instantiated', function (): void {
            $widget = new RecentActivityWidget;

            expect($widget)->toBeInstanceOf(RecentActivityWidget::class);
        });

        it('has correct sort order', function (): void {
            $reflection = new ReflectionProperty(RecentActivityWidget::class, 'sort');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe(3);
        });

        it('has full column span', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionProperty($widget, 'columnSpan');
            $columnSpan = $reflection->getValue($widget);

            expect($columnSpan)->toBe('full');
        });

        it('has correct heading', function (): void {
            $reflection = new ReflectionProperty(RecentActivityWidget::class, 'heading');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('Recent Permission Activity');
        });

        it('has table method', function (): void {
            expect(method_exists(RecentActivityWidget::class, 'table'))->toBeTrue();
        });

        it('table method returns Table instance', function (): void {
            $reflection = new ReflectionMethod(RecentActivityWidget::class, 'table');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe(Table::class);
        });

        it('extends TableWidget', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $parent = $reflection->getParentClass();

            expect($parent->getName())->toBe('Filament\\Widgets\\TableWidget');
        });

        it('uses PermissionAuditLog model', function (): void {
            // Read the source code to verify it uses PermissionAuditLog
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain('PermissionAuditLog::query()');
        });

        it('orders by created_at descending', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain("orderBy('created_at', 'desc')");
        });

        it('limits results to 10', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain('limit(10)');
        });

        it('is not paginated', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain('paginated(false)');
        });

        it('has created_at column', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain("make('created_at')");
        });

        it('has event_type column', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain("make('event_type')");
        });

        it('has severity column', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain("make('severity')");
        });
    });
});
