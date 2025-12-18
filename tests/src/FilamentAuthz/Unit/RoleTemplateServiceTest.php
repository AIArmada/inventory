<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\RoleTemplate;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    RoleTemplate::query()->delete();
    Role::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Create test user
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    // Create permissions
    $permissions = ['orders.view', 'orders.create', 'products.view', 'products.create'];
    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    test()->service = app(RoleTemplateService::class);
});

describe('RoleTemplateService → createTemplate', function (): void {
    it('creates a basic template', function (): void {
        $template = test()->service->createTemplate(
            name: 'Admin Template',
            guardName: 'web',
        );

        expect($template)->toBeInstanceOf(RoleTemplate::class)
            ->and($template->name)->toBe('Admin Template')
            ->and($template->slug)->toBe('admin-template')
            ->and($template->guard_name)->toBe('web')
            ->and($template->is_active)->toBeTrue();
    });

    it('creates a template with description', function (): void {
        $template = test()->service->createTemplate(
            name: 'Admin',
            description: 'Full administrative access',
        );

        expect($template->description)->toBe('Full administrative access');
    });

    it('creates a template with default permissions', function (): void {
        $template = test()->service->createTemplate(
            name: 'Editor',
            defaultPermissions: ['orders.view', 'products.view'],
        );

        expect($template->default_permissions)->toBe(['orders.view', 'products.view']);
    });

    it('creates a template with metadata', function (): void {
        $metadata = ['color' => 'blue', 'icon' => 'shield'];
        $template = test()->service->createTemplate(
            name: 'Custom',
            metadata: $metadata,
        );

        expect($template->metadata)->toBe($metadata);
    });

    it('creates a system template', function (): void {
        $template = test()->service->createTemplate(
            name: 'System Template',
            isSystem: true,
        );

        expect($template->is_system)->toBeTrue();
    });
});

describe('RoleTemplateService → updateTemplate', function (): void {
    it('updates template name and regenerates slug', function (): void {
        $template = test()->service->createTemplate(name: 'Original Name');

        $updated = test()->service->updateTemplate($template, [
            'name' => 'Updated Name',
        ]);

        expect($updated->name)->toBe('Updated Name')
            ->and($updated->slug)->toBe('updated-name');
    });

    it('updates template permissions', function (): void {
        $template = test()->service->createTemplate(
            name: 'Editor',
            defaultPermissions: ['orders.view'],
        );

        $updated = test()->service->updateTemplate($template, [
            'default_permissions' => ['orders.view', 'orders.create'],
        ]);

        expect($updated->default_permissions)->toBe(['orders.view', 'orders.create']);
    });

    it('updates template description', function (): void {
        $template = test()->service->createTemplate(name: 'Test');

        $updated = test()->service->updateTemplate($template, [
            'description' => 'New description',
        ]);

        expect($updated->description)->toBe('New description');
    });
});

describe('RoleTemplateService → deleteTemplate', function (): void {
    it('deletes a template', function (): void {
        $template = test()->service->createTemplate(name: 'To Delete');

        $result = test()->service->deleteTemplate($template);

        expect($result)->toBeTrue()
            ->and(RoleTemplate::find($template->id))->toBeNull();
    });
});

describe('RoleTemplateService → getRootTemplates', function (): void {
    it('returns templates without parent', function (): void {
        test()->service->createTemplate(name: 'Root 1');
        test()->service->createTemplate(name: 'Root 2');

        $roots = test()->service->getRootTemplates();

        expect($roots)->toBeInstanceOf(Collection::class)
            ->and($roots->count())->toBe(2);
    });

    it('excludes inactive templates', function (): void {
        $active = test()->service->createTemplate(name: 'Active');
        $inactive = test()->service->createTemplate(name: 'Inactive');
        test()->service->updateTemplate($inactive, ['is_active' => false]);

        $roots = test()->service->getRootTemplates();

        expect($roots->count())->toBe(1)
            ->and($roots->first()->name)->toBe('Active');
    });
});

describe('RoleTemplateService → findBySlug', function (): void {
    it('finds template by slug', function (): void {
        test()->service->createTemplate(name: 'Admin Template');

        $template = test()->service->findBySlug('admin-template');

        expect($template)->not->toBeNull()
            ->and($template->name)->toBe('Admin Template');
    });

    it('returns null for non-existent slug', function (): void {
        $template = test()->service->findBySlug('nonexistent');

        expect($template)->toBeNull();
    });
});

describe('RoleTemplateService → getActiveTemplates', function (): void {
    it('returns only active templates', function (): void {
        test()->service->createTemplate(name: 'Active 1');
        test()->service->createTemplate(name: 'Active 2');
        $inactive = test()->service->createTemplate(name: 'Inactive');
        test()->service->updateTemplate($inactive, ['is_active' => false]);

        $templates = test()->service->getActiveTemplates();

        expect($templates->count())->toBe(2);
    });
});

describe('RoleTemplateService → getByGuard', function (): void {
    it('returns templates filtered by guard', function (): void {
        test()->service->createTemplate(name: 'Web Template', guardName: 'web');
        test()->service->createTemplate(name: 'API Template', guardName: 'api');

        $webTemplates = test()->service->getByGuard('web');
        $apiTemplates = test()->service->getByGuard('api');

        expect($webTemplates->count())->toBe(1)
            ->and($webTemplates->first()->name)->toBe('Web Template')
            ->and($apiTemplates->count())->toBe(1)
            ->and($apiTemplates->first()->name)->toBe('API Template');
    });
});

describe('RoleTemplateService → cloneTemplate', function (): void {
    it('creates a copy of a template with new name', function (): void {
        $original = test()->service->createTemplate(
            name: 'Original',
            description: 'Original description',
            defaultPermissions: ['orders.view'],
            metadata: ['key' => 'value'],
        );

        $clone = test()->service->cloneTemplate($original, 'Clone');

        expect($clone->name)->toBe('Clone')
            ->and($clone->slug)->toBe('clone')
            ->and($clone->description)->toBe('Original description')
            ->and($clone->default_permissions)->toBe(['orders.view'])
            ->and($clone->metadata)->toBe(['key' => 'value'])
            ->and($clone->is_system)->toBeFalse(); // Clone is never system
    });
});

describe('RoleTemplateService → clearCache', function (): void {
    it('clears hierarchy tree cache', function (): void {
        Cache::shouldReceive('forget')
            ->once()
            ->with('permissions:templates:hierarchy_tree');

        test()->service->clearCache();
    });
});

describe('RoleTemplateService → getHierarchyTree', function (): void {
    it('returns root templates with children loaded', function (): void {
        test()->service->createTemplate(name: 'Root 1');
        test()->service->createTemplate(name: 'Root 2');

        // Clear cache to force fresh query
        Cache::forget('permissions:templates:hierarchy_tree');

        $tree = test()->service->getHierarchyTree();

        expect($tree)->toBeInstanceOf(Collection::class)
            ->and($tree->count())->toBe(2);
    });
});

describe('RoleTemplateService → createRoleFromTemplate', function (): void {
    it('creates a role from template with default permissions', function (): void {
        $template = test()->service->createTemplate(
            name: 'Admin Template',
            defaultPermissions: ['orders.view', 'products.view'],
        );

        $role = test()->service->createRoleFromTemplate($template, 'Admin');

        expect($role)->toBeInstanceOf(Role::class)
            ->and($role->name)->toBe('Admin')
            ->and($role->guard_name)->toBe('web')
            ->and($role->template_id)->toBe($template->id)
            ->and($role->permissions->pluck('name')->toArray())->toContain('orders.view', 'products.view');
    });

    it('creates a role with overrides', function (): void {
        $template = test()->service->createTemplate(
            name: 'Template',
            description: 'Default description',
        );

        $role = test()->service->createRoleFromTemplate($template, 'Custom Role', [
            'description' => 'Custom description',
        ]);

        expect($role->description)->toBe('Custom description');
    });
});

describe('RoleTemplateService → syncRoleWithTemplate', function (): void {
    it('syncs role permissions with template', function (): void {
        $template = test()->service->createTemplate(
            name: 'Editor Template',
            defaultPermissions: ['orders.view', 'orders.create'],
        );

        // Create role manually with template_id
        $role = Role::create([
            'name' => 'Editor',
            'guard_name' => 'web',
            'template_id' => $template->id,
        ]);

        // Give role different permissions
        $role->syncPermissions(['products.view']);

        // Sync with template
        $syncedRole = test()->service->syncRoleWithTemplate($role);

        expect($syncedRole)->not->toBeNull()
            ->and($syncedRole->permissions->pluck('name')->toArray())->toContain('orders.view', 'orders.create')
            ->and($syncedRole->permissions->pluck('name')->toArray())->not->toContain('products.view');
    });

    it('returns null for role without template_id', function (): void {
        $role = Role::create([
            'name' => 'Standalone',
            'guard_name' => 'web',
        ]);

        $result = test()->service->syncRoleWithTemplate($role);

        expect($result)->toBeNull();
    });

    it('returns null for role with non-existent template_id', function (): void {
        $role = Role::create([
            'name' => 'Orphan',
            'guard_name' => 'web',
            'template_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $result = test()->service->syncRoleWithTemplate($role);

        expect($result)->toBeNull();
    });
});

describe('RoleTemplateService → syncAllRolesFromTemplate', function (): void {
    it('syncs all roles using the template', function (): void {
        $template = test()->service->createTemplate(
            name: 'Base Template',
            defaultPermissions: ['orders.view'],
        );

        // Create multiple roles from template
        Role::create(['name' => 'Role1', 'guard_name' => 'web', 'template_id' => $template->id]);
        Role::create(['name' => 'Role2', 'guard_name' => 'web', 'template_id' => $template->id]);

        $result = test()->service->syncAllRolesFromTemplate($template);

        expect($result)->toBe(['synced' => 2, 'failed' => 0]);
    });

    it('returns zero counts when no roles use template', function (): void {
        $template = test()->service->createTemplate(name: 'Unused Template');

        $result = test()->service->syncAllRolesFromTemplate($template);

        expect($result)->toBe(['synced' => 0, 'failed' => 0]);
    });
});

describe('RoleTemplateService → getRolesFromTemplate', function (): void {
    it('returns all roles created from a template', function (): void {
        $template = test()->service->createTemplate(name: 'Template');

        Role::create(['name' => 'Role1', 'guard_name' => 'web', 'template_id' => $template->id]);
        Role::create(['name' => 'Role2', 'guard_name' => 'web', 'template_id' => $template->id]);
        Role::create(['name' => 'Other', 'guard_name' => 'web']); // Not from template

        $roles = test()->service->getRolesFromTemplate($template);

        expect($roles->count())->toBe(2)
            ->and($roles->pluck('name')->toArray())->toContain('Role1', 'Role2')
            ->and($roles->pluck('name')->toArray())->not->toContain('Other');
    });

    it('returns empty collection when no roles use template', function (): void {
        $template = test()->service->createTemplate(name: 'Empty Template');

        $roles = test()->service->getRolesFromTemplate($template);

        expect($roles)->toBeInstanceOf(Collection::class)
            ->and($roles->count())->toBe(0);
    });
});
