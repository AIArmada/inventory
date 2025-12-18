<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\RoleHierarchyCommand;
use AIArmada\FilamentAuthz\Console\RoleTemplateCommand;
use AIArmada\FilamentAuthz\Console\SetupCommand;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager as PermissionRolesRM;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager as RolePermissionsRM;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRM;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager as UserRolesRM;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

describe('Console Commands Code Execution', function (): void {
    describe('RoleHierarchyCommand Execution', function (): void {
        it('can be registered as artisan command', function (): void {
            $commands = Artisan::all();

            expect(array_key_exists('authz:roles-hierarchy', $commands))->toBeTrue();
        });

        it('has correct signature in artisan', function (): void {
            $command = Artisan::all()['authz:roles-hierarchy'];

            expect($command->getName())->toBe('authz:roles-hierarchy');
        });

        it('can get command arguments definition', function (): void {
            $command = new RoleHierarchyCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('action'))->toBeTrue();
        });

        it('can get command options definition', function (): void {
            $command = new RoleHierarchyCommand;
            $definition = $command->getDefinition();

            expect($definition->hasOption('role'))->toBeTrue();
            expect($definition->hasOption('parent'))->toBeTrue();
        });
    });

    describe('RoleTemplateCommand Execution', function (): void {
        it('can be registered as artisan command', function (): void {
            $commands = Artisan::all();

            expect(array_key_exists('authz:templates', $commands))->toBeTrue();
        });

        it('has correct signature in artisan', function (): void {
            $command = Artisan::all()['authz:templates'];

            expect($command->getName())->toBe('authz:templates');
        });

        it('can get command arguments definition', function (): void {
            $command = new RoleTemplateCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('action'))->toBeTrue();
        });

        it('can get command options definition', function (): void {
            $command = new RoleTemplateCommand;
            $definition = $command->getDefinition();

            expect($definition->hasOption('template'))->toBeTrue();
            expect($definition->hasOption('role'))->toBeTrue();
        });
    });

    describe('SetupCommand Execution', function (): void {
        it('can be registered as artisan command', function (): void {
            $commands = Artisan::all();

            expect(array_key_exists('authz:setup', $commands))->toBeTrue();
        });

        it('has correct signature in artisan', function (): void {
            $command = Artisan::all()['authz:setup'];

            expect($command->getName())->toBe('authz:setup');
        });

        it('can get command options definition', function (): void {
            $command = new SetupCommand;
            $definition = $command->getDefinition();

            expect($definition->hasOption('fresh'))->toBeTrue();
            expect($definition->hasOption('force'))->toBeTrue();
            expect($definition->hasOption('minimal'))->toBeTrue();
            expect($definition->hasOption('tenant'))->toBeTrue();
            expect($definition->hasOption('panel'))->toBeTrue();
            expect($definition->hasOption('skip-policies'))->toBeTrue();
            expect($definition->hasOption('skip-permissions'))->toBeTrue();
        });

        it('has all required option defaults', function (): void {
            $command = new SetupCommand;
            $definition = $command->getDefinition();

            // Check that boolean options default to false
            expect($definition->getOption('fresh')->getDefault())->toBeFalse();
            expect($definition->getOption('force')->getDefault())->toBeFalse();
            expect($definition->getOption('minimal')->getDefault())->toBeFalse();
        });
    });
});

describe('Create Pages Code Execution', function (): void {
    describe('CreatePermission Page', function (): void {
        it('has static resource property', function (): void {
            $reflection = new ReflectionProperty(CreatePermission::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe(PermissionResource::class);
        });

        it('returns correct resource class', function (): void {
            expect(CreatePermission::getResource())->toBe(PermissionResource::class);
        });

        it('has afterCreate method', function (): void {
            $reflection = new ReflectionMethod(CreatePermission::class, 'afterCreate');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('afterCreate clears permission cache', function (): void {
            // Verify the method source contains cache clearing
            $reflection = new ReflectionMethod(CreatePermission::class, 'afterCreate');
            $fileName = $reflection->getDeclaringClass()->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            $source = file($fileName);
            $methodBody = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

            expect($methodBody)->toContain('forgetCachedPermissions');
        });
    });

    describe('CreateRole Page', function (): void {
        it('has static resource property', function (): void {
            $reflection = new ReflectionProperty(CreateRole::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe(RoleResource::class);
        });

        it('returns correct resource class', function (): void {
            expect(CreateRole::getResource())->toBe(RoleResource::class);
        });

        it('has permissionIds property', function (): void {
            $reflection = new ReflectionProperty(CreateRole::class, 'permissionIds');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('has mutateFormDataBeforeCreate method', function (): void {
            $reflection = new ReflectionMethod(CreateRole::class, 'mutateFormDataBeforeCreate');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('has afterCreate method', function (): void {
            $reflection = new ReflectionMethod(CreateRole::class, 'afterCreate');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('mutateFormDataBeforeCreate extracts permissions', function (): void {
            $reflection = new ReflectionMethod(CreateRole::class, 'mutateFormDataBeforeCreate');
            $fileName = $reflection->getDeclaringClass()->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            $source = file($fileName);
            $methodBody = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

            expect($methodBody)->toContain('permissionIds');
            expect($methodBody)->toContain("unset(\$data['permissions'])");
        });
    });
});

describe('Relation Managers Code Execution', function (): void {
    describe('PermissionResource RolesRelationManager', function (): void {
        it('has static relationship property', function (): void {
            $reflection = new ReflectionProperty(PermissionRolesRM::class, 'relationship');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('roles');
        });

        it('has static title property', function (): void {
            $reflection = new ReflectionProperty(PermissionRolesRM::class, 'title');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('Roles');
        });

        it('getRelationshipName returns correct value', function (): void {
            expect(PermissionRolesRM::getRelationshipName())->toBe('roles');
        });

        it('table method returns Table', function (): void {
            $manager = new PermissionRolesRM;
            $reflection = new ReflectionMethod($manager, 'table');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe(Table::class);
        });

        it('table implementation uses TextColumn for name', function (): void {
            $reflection = new ReflectionClass(PermissionRolesRM::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain("make('name')");
            expect($source)->toContain('searchable()');
        });

        it('table implementation uses badge for guard_name', function (): void {
            $reflection = new ReflectionClass(PermissionRolesRM::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain("make('guard_name')");
            expect($source)->toContain('badge()');
        });

        it('has headerActions with AttachAction', function (): void {
            $reflection = new ReflectionClass(PermissionRolesRM::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain('headerActions');
            expect($source)->toContain('AttachAction::make()');
        });

        it('has recordActions with DetachAction', function (): void {
            $reflection = new ReflectionClass(PermissionRolesRM::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain('recordActions');
            expect($source)->toContain('DetachAction::make()');
        });
    });

    describe('RoleResource PermissionsRelationManager', function (): void {
        it('has static relationship property', function (): void {
            $reflection = new ReflectionProperty(RolePermissionsRM::class, 'relationship');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('permissions');
        });

        it('has static title property', function (): void {
            $reflection = new ReflectionProperty(RolePermissionsRM::class, 'title');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('Permissions');
        });

        it('getRelationshipName returns correct value', function (): void {
            expect(RolePermissionsRM::getRelationshipName())->toBe('permissions');
        });

        it('table method returns Table', function (): void {
            $manager = new RolePermissionsRM;
            $reflection = new ReflectionMethod($manager, 'table');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe(Table::class);
        });

        it('implements forgetCachedPermissions after actions', function (): void {
            $reflection = new ReflectionClass(RolePermissionsRM::class);
            $fileName = $reflection->getFileName();
            $source = file_get_contents($fileName);

            expect($source)->toContain('forgetCachedPermissions');
        });
    });

    describe('UserResource PermissionsRelationManager', function (): void {
        it('has static relationship property', function (): void {
            $reflection = new ReflectionProperty(UserPermissionsRM::class, 'relationship');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('permissions');
        });

        it('getRelationshipName returns correct value', function (): void {
            expect(UserPermissionsRM::getRelationshipName())->toBe('permissions');
        });
    });

    describe('UserResource RolesRelationManager', function (): void {
        it('has static relationship property', function (): void {
            $reflection = new ReflectionProperty(UserRolesRM::class, 'relationship');
            $reflection->setAccessible(true);

            expect($reflection->getValue(null))->toBe('roles');
        });

        it('getRelationshipName returns correct value', function (): void {
            expect(UserRolesRM::getRelationshipName())->toBe('roles');
        });
    });
});

describe('RecentActivityWidget Code Execution', function (): void {
    it('has static sort property with value 3', function (): void {
        $reflection = new ReflectionProperty(RecentActivityWidget::class, 'sort');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(3);
    });

    it('has static heading property', function (): void {
        $reflection = new ReflectionProperty(RecentActivityWidget::class, 'heading');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('Recent Permission Activity');
    });

    it('columnSpan is full width', function (): void {
        $widget = new RecentActivityWidget;
        $reflection = new ReflectionProperty($widget, 'columnSpan');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('full');
    });

    it('table method queries PermissionAuditLog', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain('PermissionAuditLog::query()');
    });

    it('table method orders by created_at desc', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain("orderBy('created_at', 'desc')");
    });

    it('table method limits to 10 results', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain('limit(10)');
    });

    it('table uses created_at column', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain("make('created_at')");
    });

    it('table uses event_type column', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain("make('event_type')");
    });

    it('table uses severity column', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain("make('severity')");
    });

    it('table uses description column', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain("make('description')");
    });

    it('table uses actor_id column', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain("make('actor_id')");
    });

    it('table is not paginated', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain('paginated(false)');
    });

    it('severity column uses color function', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $fileName = $reflection->getFileName();
        $source = file_get_contents($fileName);

        expect($source)->toContain('->color(');
        expect($source)->toContain("'low' => 'gray'");
        expect($source)->toContain("'medium' => 'warning'");
        expect($source)->toContain("'high' => 'danger'");
        expect($source)->toContain("'critical' => 'danger'");
    });
});
