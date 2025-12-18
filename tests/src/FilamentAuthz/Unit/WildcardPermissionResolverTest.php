<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::query()->delete();

    // Create test permissions
    $permissions = [
        'orders.view',
        'orders.create',
        'orders.update',
        'orders.delete',
        'products.view',
        'products.create',
        'products.update',
        'users.view',
        'users.create',
        'dashboard',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    test()->resolver = new WildcardPermissionResolver;
});

describe('WildcardPermissionResolver → isWildcard', function (): void {
    it('returns true for universal wildcard', function (): void {
        expect(test()->resolver->isWildcard('*'))->toBeTrue();
    });

    it('returns true for prefix wildcard', function (): void {
        expect(test()->resolver->isWildcard('orders.*'))->toBeTrue();
    });

    it('returns true for pattern wildcard', function (): void {
        expect(test()->resolver->isWildcard('*.view'))->toBeTrue();
    });

    it('returns false for regular permission', function (): void {
        expect(test()->resolver->isWildcard('orders.view'))->toBeFalse();
    });

    it('returns false for permission without wildcard', function (): void {
        expect(test()->resolver->isWildcard('dashboard'))->toBeFalse();
    });
});

describe('WildcardPermissionResolver → resolve', function (): void {
    it('returns single permission for non-wildcard', function (): void {
        $result = test()->resolver->resolve('orders.view');

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->toArray())->toBe(['orders.view']);
    });

    it('resolves universal wildcard to all permissions', function (): void {
        $result = test()->resolver->resolve('*');

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(10);
    });

    it('resolves prefix wildcard to matching permissions', function (): void {
        $result = test()->resolver->resolve('orders.*');

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(4)
            ->and($result->contains('orders.view'))->toBeTrue()
            ->and($result->contains('orders.create'))->toBeTrue()
            ->and($result->contains('orders.update'))->toBeTrue()
            ->and($result->contains('orders.delete'))->toBeTrue();
    });

    it('resolves pattern wildcard to matching permissions', function (): void {
        $result = test()->resolver->resolve('*.view');

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->contains('orders.view'))->toBeTrue()
            ->and($result->contains('products.view'))->toBeTrue()
            ->and($result->contains('users.view'))->toBeTrue();
    });
});

describe('WildcardPermissionResolver → matches', function (): void {
    it('matches exact permission', function (): void {
        expect(test()->resolver->matches('orders.view', 'orders.view'))->toBeTrue();
    });

    it('universal wildcard matches everything', function (): void {
        expect(test()->resolver->matches('*', 'orders.view'))->toBeTrue()
            ->and(test()->resolver->matches('*', 'products.create'))->toBeTrue()
            ->and(test()->resolver->matches('*', 'dashboard'))->toBeTrue();
    });

    it('prefix wildcard matches permissions with same prefix', function (): void {
        expect(test()->resolver->matches('orders.*', 'orders.view'))->toBeTrue()
            ->and(test()->resolver->matches('orders.*', 'orders.create'))->toBeTrue()
            ->and(test()->resolver->matches('orders.*', 'products.view'))->toBeFalse();
    });

    it('pattern wildcard matches permissions with same suffix', function (): void {
        expect(test()->resolver->matches('*.view', 'orders.view'))->toBeTrue()
            ->and(test()->resolver->matches('*.view', 'products.view'))->toBeTrue()
            ->and(test()->resolver->matches('*.view', 'orders.create'))->toBeFalse();
    });

    it('non-wildcard returns false for non-matching permissions', function (): void {
        expect(test()->resolver->matches('orders.view', 'products.view'))->toBeFalse();
    });
});

describe('WildcardPermissionResolver → getPrefixes', function (): void {
    it('returns all unique prefixes', function (): void {
        $result = test()->resolver->getPrefixes();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->contains('orders'))->toBeTrue()
            ->and($result->contains('products'))->toBeTrue()
            ->and($result->contains('users'))->toBeTrue()
            ->and($result->contains('dashboard'))->toBeFalse(); // No prefix for 'dashboard'
    });
});

describe('WildcardPermissionResolver → getByPrefix', function (): void {
    it('returns all permissions with given prefix', function (): void {
        $result = test()->resolver->getByPrefix('orders');

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(4)
            ->and($result->contains('orders.view'))->toBeTrue()
            ->and($result->contains('orders.create'))->toBeTrue();
    });

    it('returns empty collection for non-existent prefix', function (): void {
        $result = test()->resolver->getByPrefix('nonexistent');

        expect($result->isEmpty())->toBeTrue();
    });
});

describe('WildcardPermissionResolver → groupByPrefix', function (): void {
    it('groups permissions by prefix', function (): void {
        $result = test()->resolver->groupByPrefix();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->has('orders'))->toBeTrue()
            ->and($result->has('products'))->toBeTrue()
            ->and($result->has('users'))->toBeTrue()
            ->and($result->get('orders')->count())->toBe(4)
            ->and($result->get('products')->count())->toBe(3)
            ->and($result->get('users')->count())->toBe(2);
    });

    it('groups permissions without prefix under other', function (): void {
        $result = test()->resolver->groupByPrefix();

        expect($result->has('other'))->toBeTrue()
            ->and($result->get('other')->contains('dashboard'))->toBeTrue();
    });
});

describe('WildcardPermissionResolver → extractPrefix', function (): void {
    it('extracts prefix from dotted permission', function (): void {
        expect(test()->resolver->extractPrefix('orders.view'))->toBe('orders')
            ->and(test()->resolver->extractPrefix('products.create'))->toBe('products');
    });

    it('returns null for permission without dot', function (): void {
        expect(test()->resolver->extractPrefix('dashboard'))->toBeNull();
    });
});

describe('WildcardPermissionResolver → extractAction', function (): void {
    it('extracts action from dotted permission', function (): void {
        expect(test()->resolver->extractAction('orders.view'))->toBe('view')
            ->and(test()->resolver->extractAction('products.create'))->toBe('create');
    });

    it('returns null for permission without dot', function (): void {
        expect(test()->resolver->extractAction('dashboard'))->toBeNull();
    });
});

describe('WildcardPermissionResolver → buildPermission', function (): void {
    it('builds permission from components', function (): void {
        expect(test()->resolver->buildPermission('orders', 'view'))->toBe('orders.view')
            ->and(test()->resolver->buildPermission('products', 'create'))->toBe('products.create');
    });
});

describe('WildcardPermissionResolver → userHasPermission', function (): void {
    it('returns false for object without getAllPermissions method', function (): void {
        $user = new stdClass;

        expect(test()->resolver->userHasPermission($user, 'orders.view'))->toBeFalse();
    });
});

describe('WildcardPermissionResolver → clearCache', function (): void {
    it('clears the permission cache', function (): void {
        // First access to populate cache
        test()->resolver->resolve('*');

        Cache::shouldReceive('forget')
            ->once()
            ->with('permissions:wildcard_map');

        test()->resolver->clearCache();
    });
});
