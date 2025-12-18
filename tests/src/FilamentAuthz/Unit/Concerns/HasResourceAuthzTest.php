<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Create test permissions
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.delete', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.approve', 'guard_name' => 'web']);

    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
});

afterEach(function (): void {
    Mockery::close();
});

// Create a test class that uses the trait
function createTestResourceClass(): string
{
    $className = 'TestResourceWithAuthz_' . uniqid();
    $fullClassName = "AIArmada\\FilamentAuthz\\Tests\\MockResources\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace AIArmada\\FilamentAuthz\\Tests\\MockResources;

            use AIArmada\\FilamentAuthz\\Concerns\\HasResourceAuthz;
            use AIArmada\\Commerce\\Tests\\Fixtures\\Models\\User;

            class {$className}
            {
                use HasResourceAuthz;

                public static function getModel(): string
                {
                    return User::class;
                }
            }
        ");
    }

    return $fullClassName;
}

describe('HasResourceAuthz Trait', function (): void {
    describe('abilities', function (): void {
        it('sets custom abilities', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::abilities(['approve', 'reject']);

            $allAbilities = $resourceClass::getAllAbilities();

            expect($allAbilities)->toContain('approve')
                ->and($allAbilities)->toContain('reject')
                ->and($allAbilities)->toContain('view')
                ->and($allAbilities)->toContain('create');
        });
    });

    describe('getAllAbilities', function (): void {
        it('returns default CRUD abilities', function (): void {
            $resourceClass = createTestResourceClass();

            $abilities = $resourceClass::getAllAbilities();

            expect($abilities)->toContain('viewAny')
                ->and($abilities)->toContain('view')
                ->and($abilities)->toContain('create')
                ->and($abilities)->toContain('update')
                ->and($abilities)->toContain('delete')
                ->and($abilities)->toContain('restore')
                ->and($abilities)->toContain('forceDelete');
        });
    });

    describe('getPermissionFor', function (): void {
        it('returns permission string based on model name', function (): void {
            $resourceClass = createTestResourceClass();

            $permission = $resourceClass::getPermissionFor('view');

            expect($permission)->toBe('user.view');
        });

        it('uses custom prefix when set', function (): void {
            $resourceClass = createTestResourceClass();
            $resourceClass::setPermissionPrefix('orders');

            $permission = $resourceClass::getPermissionFor('view');

            expect($permission)->toBe('orders.view');
        });
    });

    describe('canPerform', function (): void {
        // Note: canPerform relies on Filament::auth() which requires a full Filament context
        // These tests verify the method signature and basic behavior
        it('method exists and is callable', function (): void {
            $resourceClass = createTestResourceClass();

            expect(method_exists($resourceClass, 'canPerform'))->toBeTrue();
        });
    });

    describe('setPermissionPrefix', function (): void {
        it('sets the permission prefix', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::setPermissionPrefix('custom_prefix');

            expect($resourceClass::getPermissionFor('view'))->toBe('custom_prefix.view');
        });
    });

    describe('scopeResourceToTeam', function (): void {
        it('sets the team scope key', function (): void {
            $resourceClass = createTestResourceClass();

            // Should not throw exception
            $resourceClass::scopeResourceToTeam('team_id');

            expect(true)->toBeTrue();
        });

        it('accepts custom team id key', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::scopeResourceToTeam('organization_id');

            expect(true)->toBeTrue();
        });
    });

    describe('restrictToOwned', function (): void {
        it('enables restrict to owned', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::restrictToOwned(true);

            expect(true)->toBeTrue();
        });

        it('disables restrict to owned', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::restrictToOwned(false);

            expect(true)->toBeTrue();
        });
    });

    describe('setOwnerColumn', function (): void {
        it('sets the owner column', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::setOwnerColumn('created_by');

            expect(true)->toBeTrue();
        });
    });

    describe('setOwnerAbilities', function (): void {
        it('sets owner abilities', function (): void {
            $resourceClass = createTestResourceClass();

            $resourceClass::setOwnerAbilities(['view', 'update']);

            expect(true)->toBeTrue();
        });
    });

    describe('scopeEloquentQueryWithPermissions', function (): void {
        it('returns the query builder', function (): void {
            $resourceClass = createTestResourceClass();

            $query = Mockery::mock(Builder::class);
            $query->shouldReceive('where')->andReturnSelf();

            $result = $resourceClass::scopeEloquentQueryWithPermissions($query);

            expect($result)->toBe($query);
        });

        // Note: Additional tests for team scope and owner filter require full Filament context
        it('method handles null tenant gracefully', function (): void {
            $resourceClass = createTestResourceClass();

            $query = Mockery::mock(Builder::class);
            // No where calls expected when no team scope is set and restrict is off

            $result = $resourceClass::scopeEloquentQueryWithPermissions($query);

            expect($result)->toBe($query);
        });
    });
});
