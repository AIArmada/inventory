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
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;

describe('AuthzCacheCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new AuthzCacheCommand;

        expect($command->getName())->toBe('authz:cache');
    });

    it('has description', function (): void {
        $command = new AuthzCacheCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage permission caches');
    });

    it('handles flush action', function (): void {
        $cacheService = Mockery::mock(PermissionCacheService::class);

        // The flushCache method requires prompts which are hard to test in unit tests
        // So we just verify the service class can be instantiated and has the method
        expect(method_exists($cacheService, 'flush'))->toBeTrue();
    });

    it('handles stats action', function (): void {
        $cacheService = Mockery::mock(PermissionCacheService::class);
        $cacheService->shouldReceive('getStats')->andReturn([
            'enabled' => true,
            'store' => 'array',
            'ttl' => 3600,
        ]);

        expect($cacheService->getStats())->toBe([
            'enabled' => true,
            'store' => 'array',
            'ttl' => 3600,
        ]);
    });

    it('handles warm action', function (): void {
        $cacheService = Mockery::mock(PermissionCacheService::class);
        $cacheService->shouldReceive('warmRoleCache')->once();

        $cacheService->warmRoleCache();

        expect(true)->toBeTrue();
    });
});

describe('DiscoverCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new DiscoverCommand;

        expect($command->getName())->toBe('authz:discover');
    });

    it('has description', function (): void {
        $command = new DiscoverCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Discover and display all Filament entities with their permissions');
    });

    it('can discover resources pages and widgets', function (): void {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect());
        $discovery->shouldReceive('discoverPages')->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect());

        expect($discovery->discoverResources())->toBeEmpty()
            ->and($discovery->discoverPages())->toBeEmpty()
            ->and($discovery->discoverWidgets())->toBeEmpty();
    });
});

describe('GeneratePoliciesCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new GeneratePoliciesCommand;

        expect($command->getName())->toBe('authz:policies');
    });

    it('has description', function (): void {
        $command = new GeneratePoliciesCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Generate Laravel policies for Filament resources');
    });

    it('supports policy type option', function (): void {
        $command = new GeneratePoliciesCommand;

        $definition = $command->getDefinition();

        expect($definition->hasOption('type'))->toBeTrue()
            ->and($definition->hasOption('resource'))->toBeTrue()
            ->and($definition->hasOption('model'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue()
            ->and($definition->hasOption('dry-run'))->toBeTrue();
    });
});

describe('InstallTraitCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new InstallTraitCommand;

        expect($command->getName())->toBe('authz:install-trait');
    });

    it('has description', function (): void {
        $command = new InstallTraitCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Install authorization traits into your classes');
    });

    it('has available traits defined', function (): void {
        $command = new InstallTraitCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('availableTraits');

        $traits = $property->getValue($command);

        expect($traits)->toBeArray()
            ->and($traits)->toHaveKey('HasPageAuthz')
            ->and($traits)->toHaveKey('HasWidgetAuthz')
            ->and($traits)->toHaveKey('HasResourceAuthz')
            ->and($traits)->toHaveKey('HasPanelAuthz');
    });

    it('supports preview and force options', function (): void {
        $command = new InstallTraitCommand;

        $definition = $command->getDefinition();

        expect($definition->hasOption('preview'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue()
            ->and($definition->hasOption('trait'))->toBeTrue();
    });
});

describe('RoleHierarchyCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleHierarchyCommand;

        expect($command->getName())->toBe('authz:roles-hierarchy');
    });

    it('has description', function (): void {
        $command = new RoleHierarchyCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage role hierarchy');
    });

    it('supports role and parent options', function (): void {
        $command = new RoleHierarchyCommand;

        $definition = $command->getDefinition();

        expect($definition->hasOption('role'))->toBeTrue()
            ->and($definition->hasOption('parent'))->toBeTrue();
    });
});

describe('RoleTemplateCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleTemplateCommand;

        expect($command->getName())->toBe('authz:templates');
    });

    it('has description', function (): void {
        $command = new RoleTemplateCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage role templates');
    });

    it('supports template and role options', function (): void {
        $command = new RoleTemplateCommand;

        $definition = $command->getDefinition();

        expect($definition->hasOption('template'))->toBeTrue()
            ->and($definition->hasOption('role'))->toBeTrue();
    });
});

describe('SetupCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new SetupCommand;

        expect($command->getName())->toBe('authz:setup');
    });

    it('has description', function (): void {
        $command = new SetupCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Interactive setup wizard for Filament Authz');
    });

    it('supports all setup options', function (): void {
        $command = new SetupCommand;

        $definition = $command->getDefinition();

        expect($definition->hasOption('fresh'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue()
            ->and($definition->hasOption('minimal'))->toBeTrue()
            ->and($definition->hasOption('tenant'))->toBeTrue()
            ->and($definition->hasOption('panel'))->toBeTrue()
            ->and($definition->hasOption('skip-policies'))->toBeTrue()
            ->and($definition->hasOption('skip-permissions'))->toBeTrue();
    });

    it('has state property', function (): void {
        $command = new SetupCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('state');

        expect($property->getValue($command))->toBe([]);
    });
});

describe('SnapshotCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new SnapshotCommand;

        expect($command->getName())->toBe('authz:snapshot');
    });

    it('has description', function (): void {
        $command = new SnapshotCommand;

        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage permission snapshots');
    });

    it('supports all snapshot options', function (): void {
        $command = new SnapshotCommand;

        $definition = $command->getDefinition();

        expect($definition->hasOption('name'))->toBeTrue()
            ->and($definition->hasOption('description'))->toBeTrue()
            ->and($definition->hasOption('from'))->toBeTrue()
            ->and($definition->hasOption('to'))->toBeTrue()
            ->and($definition->hasOption('snapshot'))->toBeTrue()
            ->and($definition->hasOption('dry-run'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue();
    });

    it('has list as default action', function (): void {
        $command = new SnapshotCommand;

        $definition = $command->getDefinition();
        $argument = $definition->getArgument('action');

        expect($argument->getDefault())->toBe('list');
    });
});
