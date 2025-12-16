<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PolicyGeneratorService;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\RollbackResult;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function (): void {
    \Mockery::close();
});

describe('GeneratePoliciesCommand execution', function (): void {
    it('warns and succeeds when no resources are discovered', function (): void {
        $discovery = \Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->once()->andReturn(collect());
        app()->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:policies', [
            '--type' => 'basic',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('No resources found to generate policies for')
            ->assertSuccessful();
    });

    it('generates dry-run output for discovered resources', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: User::class,
            permissions: ['viewAny', 'view'],
            metadata: [],
        );

        $discovery = \Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->once()->andReturn(collect([$resource]));
        app()->instance(EntityDiscoveryService::class, $discovery);

        $tmpPolicyPath = storage_path('testing/policies/UserPolicy.php');
        @mkdir(dirname($tmpPolicyPath), 0755, true);

        $generator = new class($tmpPolicyPath) extends PolicyGeneratorService {
            public int $calls = 0;

            public function __construct(private readonly string $path) {}

            public function generate(
                string $modelClass,
                \AIArmada\FilamentAuthz\Enums\PolicyType $type = \AIArmada\FilamentAuthz\Enums\PolicyType::Basic,
                array $options = []
            ): \AIArmada\FilamentAuthz\Services\GeneratedPolicy {
                $this->calls++;

                return new \AIArmada\FilamentAuthz\Services\GeneratedPolicy(
                    path: $this->path,
                    content: '<?php',
                    metadata: [],
                );
            }
        };

        app()->instance(PolicyGeneratorService::class, $generator);

        $this->artisan('authz:policies', [
            '--type' => 'basic',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Would generate:')
            ->assertSuccessful();

        if (file_exists($tmpPolicyPath)) {
            unlink($tmpPolicyPath);
        }
    });

    it('skips generation when policy file exists and --force is not set', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: User::class,
            permissions: ['viewAny'],
            metadata: [],
        );

        $discovery = \Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->once()->andReturn(collect([$resource]));
        app()->instance(EntityDiscoveryService::class, $discovery);

        $tmpPolicyPath = storage_path('testing/policies/UserPolicy.php');
        @mkdir(dirname($tmpPolicyPath), 0755, true);
        file_put_contents($tmpPolicyPath, "<?php\n// existing");

        $generator = new class($tmpPolicyPath) extends PolicyGeneratorService {
            public int $calls = 0;

            public function __construct(private readonly string $path) {}

            public function generate(
                string $modelClass,
                \AIArmada\FilamentAuthz\Enums\PolicyType $type = \AIArmada\FilamentAuthz\Enums\PolicyType::Basic,
                array $options = []
            ): \AIArmada\FilamentAuthz\Services\GeneratedPolicy {
                $this->calls++;

                return new \AIArmada\FilamentAuthz\Services\GeneratedPolicy(
                    path: $this->path,
                    content: "<?php\n// new",
                    metadata: [],
                );
            }
        };

        app()->instance(PolicyGeneratorService::class, $generator);

        $this->artisan('authz:policies', [
            '--type' => 'basic',
        ])
            ->expectsOutputToContain('Skipped:')
            ->assertSuccessful();

        unlink($tmpPolicyPath);
    });

    it('overwrites existing policy file when --force is set', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: User::class,
            permissions: ['viewAny'],
            metadata: [],
        );

        $discovery = \Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->once()->andReturn(collect([$resource]));
        app()->instance(EntityDiscoveryService::class, $discovery);

        $tmpPolicyPath = storage_path('testing/policies/UserPolicy.php');
        @mkdir(dirname($tmpPolicyPath), 0755, true);
        file_put_contents($tmpPolicyPath, "<?php\n// existing");

        $generator = new class($tmpPolicyPath) extends PolicyGeneratorService {
            public int $calls = 0;

            public function __construct(private readonly string $path) {}

            public function generate(
                string $modelClass,
                \AIArmada\FilamentAuthz\Enums\PolicyType $type = \AIArmada\FilamentAuthz\Enums\PolicyType::Basic,
                array $options = []
            ): \AIArmada\FilamentAuthz\Services\GeneratedPolicy {
                $this->calls++;

                return new \AIArmada\FilamentAuthz\Services\GeneratedPolicy(
                    path: $this->path,
                    content: "<?php\n// overwritten",
                    metadata: [],
                );
            }
        };

        app()->instance(PolicyGeneratorService::class, $generator);

        $this->artisan('authz:policies', [
            '--type' => 'basic',
            '--force' => true,
        ])
            ->expectsOutputToContain('Generated')
            ->assertSuccessful();

        expect(file_get_contents($tmpPolicyPath))->toContain('overwritten');

        unlink($tmpPolicyPath);
    });

    it('filters discovered resources by --resource option', function (): void {
        $resourceA = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: User::class,
            permissions: ['viewAny'],
            metadata: [],
        );

        $resourceB = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\OtherResource',
            model: 'App\\Models\\OtherModel',
            permissions: ['viewAny'],
            metadata: [],
        );

        $discovery = \Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->once()->andReturn(collect([$resourceA, $resourceB]));
        app()->instance(EntityDiscoveryService::class, $discovery);

        $generator = new class extends PolicyGeneratorService {
            public int $calls = 0;

            public function __construct() {}

            public function generate(
                string $modelClass,
                \AIArmada\FilamentAuthz\Enums\PolicyType $type = \AIArmada\FilamentAuthz\Enums\PolicyType::Basic,
                array $options = []
            ): \AIArmada\FilamentAuthz\Services\GeneratedPolicy {
                $this->calls++;

                return new \AIArmada\FilamentAuthz\Services\GeneratedPolicy(
                    path: storage_path('testing/policies/DummyPolicy.php'),
                    content: '<?php',
                    metadata: [],
                );
            }
        };

        app()->instance(PolicyGeneratorService::class, $generator);

        $this->artisan('authz:policies', [
            '--type' => 'basic',
            '--dry-run' => true,
            '--resource' => ['UserResource'],
        ])->assertSuccessful();
    });
});

describe('InstallTraitCommand execution', function (): void {
    it('fails when the provided file does not exist', function (): void {
        $this->artisan('authz:install-trait', [
            'file' => storage_path('testing/nope.php'),
            '--trait' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz',
            '--force' => true,
        ])
            ->expectsOutputToContain('File not found:')
            ->assertFailed();
    });

    it('supports preview mode without modifying the file', function (): void {
        $file = storage_path('testing/InstallTraitPreview.php');
        @mkdir(dirname($file), 0755, true);

        $original = <<<'PHP'
<?php

namespace App\Test;

class InstallTraitPreview
{
}
PHP;

        file_put_contents($file, $original);

        $this->artisan('authz:install-trait', [
            'file' => $file,
            '--trait' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz',
            '--preview' => true,
        ])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        expect(file_get_contents($file))->toBe($original);

        unlink($file);
    });

    it('does nothing when the trait is already installed', function (): void {
        $file = storage_path('testing/InstallTraitAlready.php');
        @mkdir(dirname($file), 0755, true);

        file_put_contents($file, <<<'PHP'
<?php

namespace App\Test;

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;

class InstallTraitAlready
{
    use HasPageAuthz;
}
PHP);

        $this->artisan('authz:install-trait', [
            'file' => $file,
            '--trait' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz',
            '--force' => true,
        ])
            ->expectsOutputToContain('already installed')
            ->assertSuccessful();

        unlink($file);
    });

    it('applies changes when --force is set', function (): void {
        $file = storage_path('testing/InstallTraitForce.php');
        @mkdir(dirname($file), 0755, true);

        file_put_contents($file, <<<'PHP'
<?php

namespace App\Test;

class InstallTraitForce
{
}
PHP);

        $this->artisan('authz:install-trait', [
            'file' => $file,
            '--trait' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz',
            '--force' => true,
        ])
            ->expectsOutputToContain('Successfully installed')
            ->assertSuccessful();

        $updated = file_get_contents($file);
        expect($updated)->toContain('use AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz;');
        expect($updated)->toContain('use HasPageAuthz;');

        unlink($file);
    });
});

describe('SnapshotCommand execution', function (): void {
    it('returns failure for invalid action', function (): void {
        $versioning = \Mockery::mock(PermissionVersioningService::class);
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'invalid-action',
        ])
            ->expectsOutputToContain('Unknown action')
            ->assertFailed();
    });

    it('lists snapshots with no rows', function (): void {
        $versioning = \Mockery::mock(PermissionVersioningService::class);
        $versioning->shouldReceive('listSnapshots')->once()->andReturn(new Collection());
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'list',
        ])
            ->expectsOutputToContain('No snapshots found')
            ->assertSuccessful();
    });

    it('creates snapshot with provided name', function (): void {
        $snapshot = new PermissionSnapshot();
        $snapshot->forceFill([
            'id' => 'test-snapshot-id',
            'name' => 'My Snapshot',
            'description' => null,
            'state' => ['roles' => [], 'permissions' => [], 'assignments' => []],
            'hash' => 'hash',
        ]);

        $versioning = \Mockery::mock(PermissionVersioningService::class);
        $versioning->shouldReceive('createSnapshot')->once()->with('My Snapshot', null)->andReturn($snapshot);
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'create',
            '--name' => 'My Snapshot',
        ])
            ->expectsOutputToContain('Created snapshot')
            ->assertSuccessful();
    });

    it('fails compare when ids are missing', function (): void {
        $versioning = \Mockery::mock(PermissionVersioningService::class);
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'compare',
        ])
            ->expectsOutputToContain('Both --from and --to')
            ->assertFailed();
    });

    it('renders compare output when snapshots exist', function (): void {
        $from = PermissionSnapshot::create([
            'name' => 'From',
            'description' => null,
            'created_by' => null,
            'state' => ['roles' => [], 'permissions' => [], 'assignments' => []],
            'hash' => 'from',
        ]);

        $to = PermissionSnapshot::create([
            'name' => 'To',
            'description' => null,
            'created_by' => null,
            'state' => ['roles' => [], 'permissions' => [], 'assignments' => []],
            'hash' => 'to',
        ]);

        $versioning = \Mockery::mock(PermissionVersioningService::class);
        $versioning->shouldReceive('compare')->once()->andReturn([
            'roles' => ['added' => ['Admin'], 'removed' => []],
            'permissions' => ['added' => [], 'removed' => ['orders.delete']],
            'assignments_changed' => ['added' => [], 'removed' => []],
        ]);
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'compare',
            '--from' => $from->id,
            '--to' => $to->id,
        ])
            ->expectsOutputToContain('Comparison:')
            ->assertSuccessful();
    });

    it('shows preview on dry-run rollback', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'Target',
            'description' => null,
            'created_by' => null,
            'state' => ['roles' => [], 'permissions' => [], 'assignments' => []],
            'hash' => 't',
        ]);

        $versioning = \Mockery::mock(PermissionVersioningService::class);
        $versioning->shouldReceive('previewRollback')->once()->andReturn([
            'roles' => ['added' => ['Admin'], 'removed' => []],
            'permissions' => ['added' => ['orders.view'], 'removed' => []],
            'assignments_changed' => ['added' => [], 'removed' => []],
        ]);
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => $snapshot->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Preview rollback to')
            ->assertSuccessful();
    });

    it('rolls back when --force is set', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'RollbackTarget',
            'description' => null,
            'created_by' => null,
            'state' => ['roles' => [], 'permissions' => [], 'assignments' => []],
            'hash' => 'rb',
        ]);

        $versioning = \Mockery::mock(PermissionVersioningService::class);
        $versioning->shouldReceive('rollback')->once()->andReturn(new RollbackResult(
            success: true,
            snapshot: $snapshot,
            preview: null,
            restoredAt: now(),
            isDryRun: false,
        ));
        app()->instance(PermissionVersioningService::class, $versioning);

        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => $snapshot->id,
            '--force' => true,
        ])
            ->expectsOutputToContain('Successfully rolled back')
            ->assertSuccessful();
    });
});

describe('PermissionGroupsCommand execution', function (): void {
    it('returns failure on unknown action', function (): void {
        app()->instance(PermissionGroupService::class, \Mockery::mock(PermissionGroupService::class));

        $this->artisan('authz:groups', [
            'action' => 'nope',
        ])
            ->expectsOutputToContain('Unknown action')
            ->assertFailed();
    });

    it('lists groups successfully when there are none', function (): void {
        app()->instance(PermissionGroupService::class, \Mockery::mock(PermissionGroupService::class));

        $this->artisan('authz:groups', [
            'action' => 'list',
        ])
            ->expectsOutputToContain('No permission groups found')
            ->assertSuccessful();
    });

    it('shows error when group is not found', function (): void {
        $service = \Mockery::mock(PermissionGroupService::class);
        $service->shouldReceive('findBySlug')->once()->with('missing')->andReturn(null);
        app()->instance(PermissionGroupService::class, $service);

        $this->artisan('authz:groups', [
            'action' => 'show',
            '--group' => 'missing',
        ])
            ->expectsOutputToContain('Group not found')
            ->assertFailed();
    });

    it('shows details for a group with permissions and children', function (): void {
        $perm = Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);

        $parent = PermissionGroup::create([
            'name' => 'Parent',
            'slug' => 'parent',
            'description' => null,
            'parent_id' => null,
            'sort_order' => 0,
            'is_system' => false,
        ]);

        $child = PermissionGroup::create([
            'name' => 'Child',
            'slug' => 'child',
            'description' => null,
            'parent_id' => $parent->id,
            'sort_order' => 0,
            'is_system' => false,
        ]);

        $parent->permissions()->attach($perm);
        $parent->load('permissions', 'children', 'parent');

        $service = \Mockery::mock(PermissionGroupService::class);
        $service->shouldReceive('findBySlug')->once()->with('parent')->andReturn($parent);
        app()->instance(PermissionGroupService::class, $service);

        $this->artisan('authz:groups', [
            'action' => 'show',
            '--group' => 'parent',
        ])
            ->expectsOutputToContain('Group: Parent')
            ->expectsOutputToContain('orders.view')
            ->expectsOutputToContain('Child Groups')
            ->assertSuccessful();
    });

    it('prevents deleting a system group', function (): void {
        $group = PermissionGroup::create([
            'name' => 'System',
            'slug' => 'system',
            'description' => null,
            'parent_id' => null,
            'sort_order' => 0,
            'is_system' => true,
        ]);

        $service = \Mockery::mock(PermissionGroupService::class);
        $service->shouldReceive('findBySlug')->once()->with('system')->andReturn($group);
        app()->instance(PermissionGroupService::class, $service);

        $this->artisan('authz:groups', [
            'action' => 'delete',
            '--group' => 'system',
        ])
            ->expectsOutputToContain('Cannot delete a system group')
            ->assertFailed();
    });

    it('warns when syncing but no permissions exist', function (): void {
        $group = PermissionGroup::create([
            'name' => 'Normal',
            'slug' => 'normal',
            'description' => null,
            'parent_id' => null,
            'sort_order' => 0,
            'is_system' => false,
        ]);

        $service = \Mockery::mock(PermissionGroupService::class);
        $service->shouldReceive('findBySlug')->once()->with('normal')->andReturn($group);
        app()->instance(PermissionGroupService::class, $service);

        $this->artisan('authz:groups', [
            'action' => 'sync',
            '--group' => 'normal',
        ])
            ->expectsOutputToContain('No permissions available in the database')
            ->assertSuccessful();
    });
});

describe('RoleHierarchyCommand execution', function (): void {
    it('lists roles and succeeds when there are none', function (): void {
        $service = \Mockery::mock(RoleInheritanceService::class);
        app()->instance(RoleInheritanceService::class, $service);

        $this->artisan('authz:roles-hierarchy', [
            'action' => 'list',
        ])->assertSuccessful();
    });

    it('shows tree and succeeds when there are no root roles', function (): void {
        $service = \Mockery::mock(RoleInheritanceService::class);
        $service->shouldReceive('getRootRoles')->once()->andReturn(new \Illuminate\Database\Eloquent\Collection());
        app()->instance(RoleInheritanceService::class, $service);

        $this->artisan('authz:roles-hierarchy', [
            'action' => 'tree',
        ])->assertSuccessful();
    });
});

describe('RoleTemplateCommand execution', function (): void {
    it('lists templates and succeeds when there are none', function (): void {
        $service = \Mockery::mock(RoleTemplateService::class);
        $service->shouldReceive('getActiveTemplates')->once()->andReturn(new \Illuminate\Database\Eloquent\Collection());
        app()->instance(RoleTemplateService::class, $service);

        $this->artisan('authz:templates', [
            'action' => 'list',
        ])->assertSuccessful();
    });

    it('fails create-role when template is not found', function (): void {
        $service = \Mockery::mock(RoleTemplateService::class);
        $service->shouldReceive('findBySlug')->once()->with('missing')->andReturn(null);
        app()->instance(RoleTemplateService::class, $service);

        $this->artisan('authz:templates', [
            'action' => 'create-role',
            '--template' => 'missing',
            '--role' => 'My Role',
        ])->assertFailed();
    });

    it('fails sync when role is not linked to any template', function (): void {
        Role::create(['name' => 'LinkedRole', 'guard_name' => 'web']);

        $service = \Mockery::mock(RoleTemplateService::class);
        $service->shouldReceive('syncRoleWithTemplate')->once()->andReturn(null);
        app()->instance(RoleTemplateService::class, $service);

        $this->artisan('authz:templates', [
            'action' => 'sync',
            '--role' => 'LinkedRole',
        ])->assertFailed();
    });
});
