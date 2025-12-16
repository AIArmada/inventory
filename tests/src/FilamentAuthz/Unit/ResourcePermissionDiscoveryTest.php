<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Contracts\RegistersPermissions;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Support\ResourcePermissionDiscovery;
use Filament\Panel;
use Filament\Resources\Resource;

class ResourcePermissionDiscoveryTestResourceWithoutInterface extends Resource
{
}

class ResourcePermissionDiscoveryTestResourceWithWildcard extends Resource implements RegistersPermissions
{
    public static function getPermissionKey(): string
    {
        return 'posts';
    }

    public static function getPermissionAbilities(): array
    {
        return ['viewAny', 'view', 'create'];
    }

    public static function getPermissionGroup(): ?string
    {
        return 'content';
    }

    public static function shouldRegisterWildcard(): bool
    {
        return true;
    }
}

class ResourcePermissionDiscoveryTestResourceNoWildcard extends Resource implements RegistersPermissions
{
    public static function getPermissionKey(): string
    {
        return 'comments';
    }

    public static function getPermissionAbilities(): array
    {
        return ['viewAny', 'delete'];
    }

    public static function getPermissionGroup(): ?string
    {
        return null;
    }

    public static function shouldRegisterWildcard(): bool
    {
        return false;
    }
}

afterEach(function (): void {
    \Mockery::close();
});

describe('ResourcePermissionDiscovery', function (): void {
    it('discovers and registers permissions from a panel', function (): void {
        $registry = \Mockery::mock(PermissionRegistry::class);
        $registry->shouldReceive('registerResource')
            ->once()
            ->with('posts', ['viewAny', 'view', 'create'], 'content');
        $registry->shouldReceive('registerWildcard')
            ->once()
            ->with('posts', null, 'content');

        $panel = \Mockery::mock(Panel::class);
        $panel->shouldReceive('getResources')->once()->andReturn([
            ResourcePermissionDiscoveryTestResourceWithoutInterface::class,
            ResourcePermissionDiscoveryTestResourceWithWildcard::class,
        ]);

        $service = new ResourcePermissionDiscovery($registry);

        $result = $service->discoverFromPanel($panel);

        expect($result['discovered'])->toBe(1);
        expect($result['permissions'])->toBe(4); // 3 abilities + wildcard
    });

    it('registers resource permissions without wildcard when disabled', function (): void {
        $registry = \Mockery::mock(PermissionRegistry::class);
        $registry->shouldReceive('registerResource')
            ->once()
            ->with('comments', ['viewAny', 'delete'], null);
        $registry->shouldNotReceive('registerWildcard');

        $service = new ResourcePermissionDiscovery($registry);

        $count = $service->registerPermissionsForResource(ResourcePermissionDiscoveryTestResourceNoWildcard::class);

        expect($count)->toBe(2);
    });

    it('returns the permission abilities for a resource', function (): void {
        $registry = \Mockery::mock(PermissionRegistry::class);
        $service = new ResourcePermissionDiscovery($registry);

        expect($service->getPermissionsForResource(ResourcePermissionDiscoveryTestResourceWithWildcard::class))
            ->toBe(['viewAny', 'view', 'create']);
    });
});
