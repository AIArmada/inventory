<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Contracts\RegistersPermissions;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Support\ResourcePermissionDiscovery;
use Filament\Panel;
use Filament\Resources\Resource;

beforeEach(function (): void {
    test()->registry = Mockery::mock(PermissionRegistry::class);
    test()->discovery = new ResourcePermissionDiscovery(test()->registry);
});

afterEach(function (): void {
    Mockery::close();
});

describe('ResourcePermissionDiscovery → discoverFromPanel', function (): void {
    it('returns zero counts for empty panel', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getResources')
            ->once()
            ->andReturn([]);

        $result = test()->discovery->discoverFromPanel($panel);

        expect($result)->toBe([
            'discovered' => 0,
            'permissions' => 0,
        ]);
    });

    it('ignores resources that do not implement RegistersPermissions', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getResources')
            ->once()
            ->andReturn([NonRegisteringResource::class]);

        $result = test()->discovery->discoverFromPanel($panel);

        expect($result)->toBe([
            'discovered' => 0,
            'permissions' => 0,
        ]);
    });

    it('registers permissions for resources implementing RegistersPermissions', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getResources')
            ->once()
            ->andReturn([TestRegisteringResource::class]);

        test()->registry->shouldReceive('registerResource')
            ->once()
            ->with('test', ['view', 'create', 'update', 'delete'], 'Test Group');

        test()->registry->shouldReceive('registerWildcard')
            ->once()
            ->with('test', null, 'Test Group');

        $result = test()->discovery->discoverFromPanel($panel);

        expect($result['discovered'])->toBe(1);
    });
});

describe('ResourcePermissionDiscovery → discoverFromNamespaces', function (): void {
    it('returns zero counts for empty namespace array', function (): void {
        $result = test()->discovery->discoverFromNamespaces([]);

        expect($result)->toBe([
            'discovered' => 0,
            'permissions' => 0,
        ]);
    });
});

describe('ResourcePermissionDiscovery → registerPermissionsForResource', function (): void {
    it('registers resource permissions', function (): void {
        test()->registry->shouldReceive('registerResource')
            ->once()
            ->with('test', ['view', 'create', 'update', 'delete'], 'Test Group');

        test()->registry->shouldReceive('registerWildcard')
            ->once()
            ->with('test', null, 'Test Group');

        $count = test()->discovery->registerPermissionsForResource(TestRegisteringResource::class);

        // 4 abilities + 1 wildcard
        expect($count)->toBe(5);
    });

    it('skips wildcard registration when disabled', function (): void {
        test()->registry->shouldReceive('registerResource')
            ->once()
            ->with('no_wildcard', ['view'], 'No Wildcard Group');

        // No wildcard registration expected

        $count = test()->discovery->registerPermissionsForResource(NoWildcardResource::class);

        // Only 1 ability, no wildcard
        expect($count)->toBe(1);
    });
});

describe('ResourcePermissionDiscovery → getPermissionsForResource', function (): void {
    it('returns resource permission abilities', function (): void {
        $permissions = test()->discovery->getPermissionsForResource(TestRegisteringResource::class);

        expect($permissions)->toBe(['view', 'create', 'update', 'delete']);
    });
});

// Test classes

class NonRegisteringResource extends Resource
{
    protected static ?string $model = null;
}

class TestRegisteringResource extends Resource implements RegistersPermissions
{
    protected static ?string $model = null;

    public static function getPermissionKey(): string
    {
        return 'test';
    }

    /**
     * @return array<string>
     */
    public static function getPermissionAbilities(): array
    {
        return ['view', 'create', 'update', 'delete'];
    }

    public static function getPermissionGroup(): ?string
    {
        return 'Test Group';
    }

    public static function shouldRegisterWildcard(): bool
    {
        return true;
    }
}

class NoWildcardResource extends Resource implements RegistersPermissions
{
    protected static ?string $model = null;

    public static function getPermissionKey(): string
    {
        return 'no_wildcard';
    }

    /**
     * @return array<string>
     */
    public static function getPermissionAbilities(): array
    {
        return ['view'];
    }

    public static function getPermissionGroup(): ?string
    {
        return 'No Wildcard Group';
    }

    public static function shouldRegisterWildcard(): bool
    {
        return false;
    }
}
