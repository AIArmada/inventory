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
use Illuminate\Console\Command;

describe('AuthzCacheCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new AuthzCacheCommand;
        expect($command->getName())->toBe('authz:cache');
    });

    it('has correct description', function (): void {
        $command = new AuthzCacheCommand;
        expect($command->getDescription())->toBe('Manage permission caches');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(AuthzCacheCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(AuthzCacheCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(AuthzCacheCommand::class);
        expect($reflection->hasMethod('flushCache'))->toBeTrue()
            ->and($reflection->hasMethod('warmCache'))->toBeTrue()
            ->and($reflection->hasMethod('showStats'))->toBeTrue();
    });
});

describe('DiscoverCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new DiscoverCommand;
        expect($command->getName())->toBe('authz:discover');
    });

    it('has correct description', function (): void {
        $command = new DiscoverCommand;
        expect($command->getDescription())->toBe('Discover and display all Filament entities with their permissions');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(DiscoverCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(DiscoverCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(DiscoverCommand::class);
        expect($reflection->hasMethod('displayTable'))->toBeTrue()
            ->and($reflection->hasMethod('outputJson'))->toBeTrue()
            ->and($reflection->hasMethod('generatePermissions'))->toBeTrue();
    });
});

describe('GeneratePoliciesCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new GeneratePoliciesCommand;
        expect($command->getName())->toBe('authz:policies');
    });

    it('has correct description', function (): void {
        $command = new GeneratePoliciesCommand;
        expect($command->getDescription())->toBe('Generate Laravel policies for Filament resources');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(GeneratePoliciesCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(GeneratePoliciesCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(GeneratePoliciesCommand::class);
        expect($reflection->hasMethod('getPolicyType'))->toBeTrue()
            ->and($reflection->hasMethod('getResources'))->toBeTrue();
    });
});

describe('InstallTraitCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new InstallTraitCommand;
        expect($command->getName())->toBe('authz:install-trait');
    });

    it('has correct description', function (): void {
        $command = new InstallTraitCommand;
        expect($command->getDescription())->toBe('Install authorization traits into your classes');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(InstallTraitCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(InstallTraitCommand::class, 'handle'))->toBeTrue();
    });

    it('has available traits configuration', function (): void {
        $reflection = new ReflectionClass(InstallTraitCommand::class);
        $property = $reflection->getProperty('availableTraits');
        $command = new InstallTraitCommand;
        $traits = $property->getValue($command);

        expect($traits)->toBeArray()
            ->and($traits)->toHaveKey('HasPageAuthz')
            ->and($traits)->toHaveKey('HasWidgetAuthz')
            ->and($traits)->toHaveKey('HasResourceAuthz')
            ->and($traits)->toHaveKey('HasPanelAuthz');
    });
});

describe('RoleHierarchyCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleHierarchyCommand;
        expect($command->getName())->toBe('authz:roles-hierarchy');
    });

    it('has correct description', function (): void {
        $command = new RoleHierarchyCommand;
        expect($command->getDescription())->toBe('Manage role hierarchy');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(RoleHierarchyCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(RoleHierarchyCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyCommand::class);
        expect($reflection->hasMethod('listRoles'))->toBeTrue()
            ->and($reflection->hasMethod('showTree'))->toBeTrue()
            ->and($reflection->hasMethod('setParent'))->toBeTrue()
            ->and($reflection->hasMethod('detachFromParent'))->toBeTrue()
            ->and($reflection->hasMethod('searchRole'))->toBeTrue();
    });
});

describe('RoleTemplateCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleTemplateCommand;
        expect($command->getName())->toBe('authz:templates');
    });

    it('has correct description', function (): void {
        $command = new RoleTemplateCommand;
        expect($command->getDescription())->toBe('Manage role templates');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(RoleTemplateCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(RoleTemplateCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(RoleTemplateCommand::class);
        expect($reflection->hasMethod('listTemplates'))->toBeTrue()
            ->and($reflection->hasMethod('createTemplate'))->toBeTrue()
            ->and($reflection->hasMethod('createRoleFromTemplate'))->toBeTrue()
            ->and($reflection->hasMethod('syncRole'))->toBeTrue()
            ->and($reflection->hasMethod('syncAllRoles'))->toBeTrue()
            ->and($reflection->hasMethod('deleteTemplate'))->toBeTrue()
            ->and($reflection->hasMethod('searchTemplate'))->toBeTrue()
            ->and($reflection->hasMethod('getPermissionOptions'))->toBeTrue();
    });
});

describe('SetupCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new SetupCommand;
        expect($command->getName())->toBe('authz:setup');
    });

    it('has correct description', function (): void {
        $command = new SetupCommand;
        expect($command->getDescription())->toBe('Interactive setup wizard for Filament Authz');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(SetupCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(SetupCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(SetupCommand::class);
        expect($reflection->hasMethod('isProhibited'))->toBeTrue()
            ->and($reflection->hasMethod('welcome'))->toBeTrue()
            ->and($reflection->hasMethod('detectEnvironment'))->toBeTrue()
            ->and($reflection->hasMethod('configurePackage'))->toBeTrue()
            ->and($reflection->hasMethod('setupDatabase'))->toBeTrue()
            ->and($reflection->hasMethod('setupRoles'))->toBeTrue()
            ->and($reflection->hasMethod('setupPermissions'))->toBeTrue()
            ->and($reflection->hasMethod('setupPolicies'))->toBeTrue()
            ->and($reflection->hasMethod('setupSuperAdmin'))->toBeTrue()
            ->and($reflection->hasMethod('verify'))->toBeTrue()
            ->and($reflection->hasMethod('showCompletion'))->toBeTrue()
            ->and($reflection->hasMethod('displayDetection'))->toBeTrue()
            ->and($reflection->hasMethod('publishConfig'))->toBeTrue();
    });

    it('has state property', function (): void {
        $reflection = new ReflectionClass(SetupCommand::class);
        expect($reflection->hasProperty('state'))->toBeTrue();
    });
});

describe('SnapshotCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new SnapshotCommand;
        expect($command->getName())->toBe('authz:snapshot');
    });

    it('has correct description', function (): void {
        $command = new SnapshotCommand;
        expect($command->getDescription())->toBe('Manage permission snapshots');
    });

    it('extends Command', function (): void {
        expect(is_subclass_of(SnapshotCommand::class, Command::class))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(SnapshotCommand::class, 'handle'))->toBeTrue();
    });

    it('has protected helper methods', function (): void {
        $reflection = new ReflectionClass(SnapshotCommand::class);
        expect($reflection->hasMethod('createSnapshot'))->toBeTrue()
            ->and($reflection->hasMethod('listSnapshots'))->toBeTrue()
            ->and($reflection->hasMethod('compareSnapshots'))->toBeTrue()
            ->and($reflection->hasMethod('rollbackSnapshot'))->toBeTrue()
            ->and($reflection->hasMethod('invalidAction'))->toBeTrue();
    });
});
