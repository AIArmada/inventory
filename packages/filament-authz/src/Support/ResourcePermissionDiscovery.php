<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\FilamentAuthz\Contracts\RegistersPermissions;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;

/**
 * Discovers and registers permissions from Filament resources.
 *
 * This service scans registered resources in a Filament panel and automatically
 * registers their permissions when they implement RegistersPermissions.
 */
final class ResourcePermissionDiscovery
{
    public function __construct(
        private PermissionRegistry $registry
    ) {}

    /**
     * Discover and register permissions from all resources in a panel.
     *
     * @return array{discovered: int, permissions: int}
     */
    public function discoverFromPanel(Panel $panel): array
    {
        $resources = $this->getResourcesFromPanel($panel);
        $discoveredCount = 0;
        $permissionCount = 0;

        foreach ($resources as $resourceClass) {
            if ($this->implementsRegistersPermissions($resourceClass)) {
                /** @var class-string<resource&RegistersPermissions> $resourceClass */
                $count = $this->registerPermissionsForResource($resourceClass);
                $discoveredCount++;
                $permissionCount += $count;
            }
        }

        return [
            'discovered' => $discoveredCount,
            'permissions' => $permissionCount,
        ];
    }

    /**
     * Discover permissions from configured namespaces.
     *
     * @param  array<string>  $namespaces
     * @return array{discovered: int, permissions: int}
     */
    public function discoverFromNamespaces(array $namespaces): array
    {
        $discoveredCount = 0;
        $permissionCount = 0;

        foreach ($namespaces as $namespace) {
            $classes = $this->findResourceClassesInNamespace($namespace);

            foreach ($classes as $resourceClass) {
                if ($this->implementsRegistersPermissions($resourceClass)) {
                    /** @var class-string<resource&RegistersPermissions> $resourceClass */
                    $count = $this->registerPermissionsForResource($resourceClass);
                    $discoveredCount++;
                    $permissionCount += $count;
                }
            }
        }

        return [
            'discovered' => $discoveredCount,
            'permissions' => $permissionCount,
        ];
    }

    /**
     * Register permissions for a single resource.
     *
     * @param  class-string<resource&RegistersPermissions>  $resourceClass
     * @return int Number of permissions registered
     */
    public function registerPermissionsForResource(string $resourceClass): int
    {
        $key = $resourceClass::getPermissionKey();
        $abilities = $resourceClass::getPermissionAbilities();
        $group = $resourceClass::getPermissionGroup();

        // Register resource permissions
        $this->registry->registerResource($key, $abilities, $group);
        $count = count($abilities);

        // Register wildcard if enabled
        if ($resourceClass::shouldRegisterWildcard()) {
            $this->registry->registerWildcard($key, null, $group);
            $count++;
        }

        return $count;
    }

    /**
     * Get all registered permissions for a resource.
     *
     * @param  class-string<resource&RegistersPermissions>  $resourceClass
     * @return array<string>
     */
    public function getPermissionsForResource(string $resourceClass): array
    {
        /** @var array<string> */
        return $resourceClass::getPermissionAbilities();
    }

    /**
     * Get resources from a panel.
     *
     * @return Collection<int, class-string<resource>>
     */
    private function getResourcesFromPanel(Panel $panel): Collection
    {
        return collect($panel->getResources());
    }

    /**
     * Check if a resource class implements RegistersPermissions.
     *
     * @param  class-string<resource>  $resourceClass
     */
    private function implementsRegistersPermissions(string $resourceClass): bool
    {
        return is_subclass_of($resourceClass, RegistersPermissions::class);
    }

    /**
     * Find resource classes in a namespace.
     *
     * @return Collection<int, class-string<resource>>
     */
    private function findResourceClassesInNamespace(string $namespace): Collection
    {
        // This is a simplified implementation
        // In production, you might use a composer classmap or PSR-4 autoloader inspection
        $classes = collect();

        // Try known filament package patterns
        $knownPackages = [
            'AIArmada\\FilamentVouchers\\Resources' => 'packages/filament-vouchers/src/Resources',
            'AIArmada\\FilamentCart\\Resources' => 'packages/filament-cart/src/Resources',
            'AIArmada\\FilamentInventory\\Resources' => 'packages/filament-inventory/src/Resources',
            'AIArmada\\FilamentAffiliates\\Resources' => 'packages/filament-affiliates/src/Resources',
            'AIArmada\\FilamentChip\\Resources' => 'packages/filament-chip/src/Resources',
            'AIArmada\\FilamentStock\\Resources' => 'packages/filament-stock/src/Resources',
            'AIArmada\\FilamentJnt\\Resources' => 'packages/filament-jnt/src/Resources',
            'AIArmada\\FilamentDocs\\Resources' => 'packages/filament-docs/src/Resources',
        ];

        if (isset($knownPackages[$namespace])) {
            $path = base_path($knownPackages[$namespace]);

            if (is_dir($path)) {
                $files = glob($path . '/*Resource.php') ?: [];

                foreach ($files as $file) {
                    $className = $namespace . '\\' . basename((string) $file, '.php');

                    if (class_exists($className) && is_subclass_of($className, Resource::class)) {
                        /** @var class-string<resource> $className */
                        $classes->push($className);
                    }
                }
            }
        }

        return $classes;
    }
}
