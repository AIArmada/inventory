<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\AuthzCacheCommand;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\InstallTraitCommand;
use AIArmada\FilamentAuthz\Console\RoleHierarchyCommand;
use AIArmada\FilamentAuthz\Console\RoleTemplateCommand;
use AIArmada\FilamentAuthz\Console\SetupCommand;
use AIArmada\FilamentAuthz\Console\SnapshotCommand;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager as UserRolesRelationManager;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\PolicyGeneratorService;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

describe('Console Commands Execution', function (): void {
    describe('AuthzCacheCommand', function (): void {
        it('executes stats action successfully', function (): void {
            $mockCacheService = Mockery::mock(PermissionCacheService::class);
            $mockCacheService->shouldReceive('getStats')->once()->andReturn([
                'enabled' => true,
                'store' => 'file',
                'ttl' => 3600,
            ]);

            app()->instance(PermissionCacheService::class, $mockCacheService);

            $exitCode = Artisan::call('authz:cache', ['action' => 'stats']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('executes warm action successfully', function (): void {
            $mockCacheService = Mockery::mock(PermissionCacheService::class);
            $mockCacheService->shouldReceive('warmRoleCache')->once();

            app()->instance(PermissionCacheService::class, $mockCacheService);

            $exitCode = Artisan::call('authz:cache', ['action' => 'warm']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('returns failure for invalid action', function (): void {
            $mockCacheService = Mockery::mock(PermissionCacheService::class);
            app()->instance(PermissionCacheService::class, $mockCacheService);

            $exitCode = Artisan::call('authz:cache', ['action' => 'invalid']);

            expect($exitCode)->toBe(Command::FAILURE);
        });

        it('has correct signature and description', function (): void {
            $command = new AuthzCacheCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:cache');
            expect($description->getValue($command))->toBe('Manage permission caches');
        });
    });

    describe('DiscoverCommand', function (): void {
        it('executes with json format option', function (): void {
            $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
            $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect());
            $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
            $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

            app()->instance(EntityDiscoveryService::class, $mockDiscovery);

            $exitCode = Artisan::call('authz:discover', ['--format' => 'json']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('executes with type filter for resources only', function (): void {
            $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
            $mockDiscovery->shouldReceive('discoverResources')->once()->andReturn(collect());

            app()->instance(EntityDiscoveryService::class, $mockDiscovery);

            $exitCode = Artisan::call('authz:discover', ['--type' => 'resources', '--format' => 'json']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('executes with type filter for pages only', function (): void {
            $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
            $mockDiscovery->shouldReceive('discoverPages')->once()->andReturn(collect());

            app()->instance(EntityDiscoveryService::class, $mockDiscovery);

            $exitCode = Artisan::call('authz:discover', ['--type' => 'pages', '--format' => 'json']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('executes with type filter for widgets only', function (): void {
            $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
            $mockDiscovery->shouldReceive('discoverWidgets')->once()->andReturn(collect());

            app()->instance(EntityDiscoveryService::class, $mockDiscovery);

            $exitCode = Artisan::call('authz:discover', ['--type' => 'widgets', '--format' => 'json']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('executes with panel filter', function (): void {
            $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
            $mockDiscovery->shouldReceive('discoverResources')
                ->with(Mockery::on(fn ($opts) => ($opts['panels'] ?? []) === ['admin']))
                ->andReturn(collect());
            $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
            $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

            app()->instance(EntityDiscoveryService::class, $mockDiscovery);

            $exitCode = Artisan::call('authz:discover', ['--panel' => 'admin', '--format' => 'json']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('has correct command properties', function (): void {
            $command = new DiscoverCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:discover');
            expect($description->getValue($command))->toContain('Discover');
        });
    });

    describe('GeneratePoliciesCommand', function (): void {
        it('executes with dry-run option', function (): void {
            $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
            $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect());

            $mockGenerator = Mockery::mock(PolicyGeneratorService::class);

            app()->instance(EntityDiscoveryService::class, $mockDiscovery);
            app()->instance(PolicyGeneratorService::class, $mockGenerator);

            $exitCode = Artisan::call('authz:policies', ['--dry-run' => true, '--type' => 'basic']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('has correct command properties', function (): void {
            $command = new GeneratePoliciesCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:policies');
            expect($description->getValue($command))->toContain('Generate');
        });
    });

    describe('InstallTraitCommand', function (): void {
        it('returns failure for non-existent file', function (): void {
            $exitCode = Artisan::call('authz:install-trait', [
                'file' => '/nonexistent/file.php',
                '--trait' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz',
            ]);

            expect($exitCode)->toBe(Command::FAILURE);
        });

        it('has correct command properties', function (): void {
            $command = new InstallTraitCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:install-trait');
            expect($description->getValue($command))->toContain('traits');
        });

        it('has available traits defined', function (): void {
            $command = new InstallTraitCommand;
            $reflection = new ReflectionClass($command);

            $traits = $reflection->getProperty('availableTraits');
            $traitsValue = $traits->getValue($command);

            expect($traitsValue)->toBeArray();
            expect($traitsValue)->toHaveKey('HasPageAuthz');
            expect($traitsValue)->toHaveKey('HasWidgetAuthz');
            expect($traitsValue)->toHaveKey('HasResourceAuthz');
            expect($traitsValue)->toHaveKey('HasPanelAuthz');
        });
    });

    describe('RoleHierarchyCommand', function (): void {
        it('has correct command properties', function (): void {
            $command = new RoleHierarchyCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:roles-hierarchy');
            expect($description->getValue($command))->toBe('Manage role hierarchy');
        });
    });

    describe('RoleTemplateCommand', function (): void {
        it('has correct command properties', function (): void {
            $command = new RoleTemplateCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:templates');
            expect($description->getValue($command))->toBe('Manage role templates');
        });
    });

    describe('SetupCommand', function (): void {
        it('has correct command properties', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:setup');
            expect($description->getValue($command))->toContain('Interactive setup wizard');
        });

        it('has state property', function (): void {
            $command = new SetupCommand;
            $reflection = new ReflectionClass($command);

            expect($reflection->hasProperty('state'))->toBeTrue();

            $state = $reflection->getProperty('state');
            expect($state->getValue($command))->toBeArray();
        });
    });

    describe('SnapshotCommand', function (): void {
        it('executes list action successfully', function (): void {
            $mockVersioning = Mockery::mock(PermissionVersioningService::class);
            $mockVersioning->shouldReceive('listSnapshots')->once()->andReturn(collect());

            app()->instance(PermissionVersioningService::class, $mockVersioning);

            $exitCode = Artisan::call('authz:snapshot', ['action' => 'list']);

            expect($exitCode)->toBe(Command::SUCCESS);
        });

        it('returns failure for compare without required options', function (): void {
            $mockVersioning = Mockery::mock(PermissionVersioningService::class);
            app()->instance(PermissionVersioningService::class, $mockVersioning);

            $exitCode = Artisan::call('authz:snapshot', ['action' => 'compare']);

            expect($exitCode)->toBe(Command::FAILURE);
        });

        it('returns failure for rollback without snapshot option', function (): void {
            $mockVersioning = Mockery::mock(PermissionVersioningService::class);
            app()->instance(PermissionVersioningService::class, $mockVersioning);

            $exitCode = Artisan::call('authz:snapshot', ['action' => 'rollback']);

            expect($exitCode)->toBe(Command::FAILURE);
        });

        it('returns failure for invalid action', function (): void {
            $mockVersioning = Mockery::mock(PermissionVersioningService::class);
            app()->instance(PermissionVersioningService::class, $mockVersioning);

            $exitCode = Artisan::call('authz:snapshot', ['action' => 'invalid-action']);

            expect($exitCode)->toBe(Command::FAILURE);
        });

        it('has correct command properties', function (): void {
            $command = new SnapshotCommand;
            $reflection = new ReflectionClass($command);

            $signature = $reflection->getProperty('signature');
            $description = $reflection->getProperty('description');

            expect($signature->getValue($command))->toContain('authz:snapshot');
            expect($description->getValue($command))->toBe('Manage permission snapshots');
        });
    });
});

describe('Resource Pages Execution', function (): void {
    describe('CreatePermission', function (): void {
        it('can be instantiated', function (): void {
            $page = new CreatePermission;

            expect($page)->toBeInstanceOf(CreatePermission::class);
        });

        it('has getResource method', function (): void {
            expect(method_exists(CreatePermission::class, 'getResource'))->toBeTrue();
        });
    });

    describe('CreateRole', function (): void {
        it('can be instantiated', function (): void {
            $page = new CreateRole;

            expect($page)->toBeInstanceOf(CreateRole::class);
        });

        it('has getResource method', function (): void {
            expect(method_exists(CreateRole::class, 'getResource'))->toBeTrue();
        });
    });
});

describe('Relation Managers Execution', function (): void {
    describe('PermissionResource RolesRelationManager', function (): void {
        it('has correct relationship property', function (): void {
            $reflection = new ReflectionClass(RolesRelationManager::class);
            $relationship = $reflection->getProperty('relationship');

            expect($relationship->getValue(null))->toBe('roles');
        });

        it('has correct title property', function (): void {
            $reflection = new ReflectionClass(RolesRelationManager::class);
            $title = $reflection->getProperty('title');

            expect($title->getValue(null))->toBe('Roles');
        });

        it('has table method', function (): void {
            expect(method_exists(RolesRelationManager::class, 'table'))->toBeTrue();
        });

        it('has form method', function (): void {
            expect(method_exists(RolesRelationManager::class, 'form'))->toBeTrue();
        });
    });

    describe('RoleResource PermissionsRelationManager', function (): void {
        it('has correct relationship property', function (): void {
            $reflection = new ReflectionClass(PermissionsRelationManager::class);
            $relationship = $reflection->getProperty('relationship');

            expect($relationship->getValue(null))->toBe('permissions');
        });

        it('has correct title property', function (): void {
            $reflection = new ReflectionClass(PermissionsRelationManager::class);
            $title = $reflection->getProperty('title');

            expect($title->getValue(null))->toBe('Permissions');
        });

        it('has table method', function (): void {
            expect(method_exists(PermissionsRelationManager::class, 'table'))->toBeTrue();
        });

        it('has form method', function (): void {
            expect(method_exists(PermissionsRelationManager::class, 'form'))->toBeTrue();
        });
    });

    describe('UserResource PermissionsRelationManager', function (): void {
        it('has correct relationship property', function (): void {
            $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
            $relationship = $reflection->getProperty('relationship');

            expect($relationship->getValue(null))->toBe('permissions');
        });

        it('has table method', function (): void {
            expect(method_exists(UserPermissionsRelationManager::class, 'table'))->toBeTrue();
        });

        it('has form method', function (): void {
            expect(method_exists(UserPermissionsRelationManager::class, 'form'))->toBeTrue();
        });
    });

    describe('UserResource RolesRelationManager', function (): void {
        it('has correct relationship property', function (): void {
            $reflection = new ReflectionClass(UserRolesRelationManager::class);
            $relationship = $reflection->getProperty('relationship');

            expect($relationship->getValue(null))->toBe('roles');
        });

        it('has table method', function (): void {
            expect(method_exists(UserRolesRelationManager::class, 'table'))->toBeTrue();
        });

        it('has form method', function (): void {
            expect(method_exists(UserRolesRelationManager::class, 'form'))->toBeTrue();
        });
    });
});

describe('Widgets Execution', function (): void {
    describe('RecentActivityWidget', function (): void {
        it('can be instantiated', function (): void {
            $widget = new RecentActivityWidget;

            expect($widget)->toBeInstanceOf(RecentActivityWidget::class);
        });

        it('has correct sort property', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $sort = $reflection->getProperty('sort');

            expect($sort->getValue(null))->toBe(3);
        });

        it('has correct column span', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $columnSpan = $reflection->getProperty('columnSpan');

            expect($columnSpan->getValue($widget))->toBe('full');
        });

        it('has correct heading', function (): void {
            $reflection = new ReflectionClass(RecentActivityWidget::class);
            $heading = $reflection->getProperty('heading');

            expect($heading->getValue(null))->toBe('Recent Permission Activity');
        });

        it('has table method', function (): void {
            expect(method_exists(RecentActivityWidget::class, 'table'))->toBeTrue();
        });

        it('extends BaseWidget', function (): void {
            expect(is_subclass_of(RecentActivityWidget::class, Filament\Widgets\TableWidget::class))->toBeTrue();
        });
    });
});
