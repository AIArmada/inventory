<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services\Discovery;

use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use Filament\Resources\Resource;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

class ResourceTransformer
{
    /**
     * @var array<string>
     */
    protected array $basePermissions = [
        'viewAny',
        'view',
        'create',
        'update',
        'delete',
    ];

    /**
     * Transform a resource class into a DiscoveredResource.
     */
    public function transform(string $resourceClass, ?string $panel = null): DiscoveredResource
    {
        if (! class_exists($resourceClass) || ! is_subclass_of($resourceClass, Resource::class)) {
            throw new InvalidArgumentException("Invalid resource class: {$resourceClass}");
        }

        /** @var resource $resource */
        $resource = app($resourceClass);

        return new DiscoveredResource(
            fqcn: $resourceClass,
            model: $resource::getModel(),
            permissions: $this->generatePermissions($resourceClass),
            metadata: $this->extractMetadata($resourceClass),
            panel: $panel,
            navigationGroup: $resource::getNavigationGroup(),
            navigationLabel: $resource::getNavigationLabel(),
            slug: $resource::getSlug(),
            cluster: $resource::getCluster(),
        );
    }

    /**
     * Generate permissions based on resource capabilities.
     *
     * @return array<string>
     */
    protected function generatePermissions(string $resourceClass): array
    {
        $permissions = $this->basePermissions;

        // Detect bulk actions
        $bulkActions = $this->detectBulkActions($resourceClass);
        foreach ($bulkActions as $action) {
            $permissions[] = 'bulk' . Str::studly($action);
        }

        // Detect table actions
        $tableActions = $this->detectTableActions($resourceClass);
        foreach ($tableActions as $action) {
            if ($this->isCustomAction($action)) {
                $permissions[] = Str::camel($action);
            }
        }

        // Detect relation managers
        $relations = $this->detectRelationManagers($resourceClass);
        foreach ($relations as $relation) {
            $relationName = Str::studly($relation);
            $permissions[] = "view{$relationName}";
            $permissions[] = "create{$relationName}";
            $permissions[] = "update{$relationName}";
            $permissions[] = "delete{$relationName}";
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Detect bulk actions on the resource table.
     *
     * @return array<string>
     */
    protected function detectBulkActions(string $resourceClass): array
    {
        $actions = [];

        try {
            $reflection = new ReflectionClass($resourceClass);
            if ($reflection->hasMethod('table')) {
                // Check for common bulk action patterns
                $source = file_get_contents($reflection->getFileName() ?: '');
                if ($source !== false) {
                    if (str_contains($source, 'DeleteBulkAction')) {
                        $actions[] = 'delete';
                    }
                    if (str_contains($source, 'ExportBulkAction')) {
                        $actions[] = 'export';
                    }
                }
            }
        } catch (Throwable) {
            // Ignore reflection errors
        }

        return $actions;
    }

    /**
     * Detect table actions on the resource.
     *
     * @return array<string>
     */
    protected function detectTableActions(string $resourceClass): array
    {
        $actions = [];

        try {
            $reflection = new ReflectionClass($resourceClass);
            if ($reflection->hasMethod('table')) {
                $source = file_get_contents($reflection->getFileName() ?: '');
                if ($source !== false) {
                    // Match Action::make('actionName') patterns
                    preg_match_all("/Action::make\(['\"](\w+)['\"]\)/", $source, $matches);
                    if (! empty($matches[1])) {
                        $actions = array_merge($actions, $matches[1]);
                    }
                }
            }
        } catch (Throwable) {
            // Ignore reflection errors
        }

        return $actions;
    }

    /**
     * Detect relation managers on the resource.
     *
     * @return array<string>
     */
    protected function detectRelationManagers(string $resourceClass): array
    {
        $relations = [];

        try {
            /** @var resource $resource */
            $resource = app($resourceClass);
            $relationManagers = $resource::getRelations();

            foreach ($relationManagers as $relationManager) {
                $relations[] = class_basename($relationManager);
            }
        } catch (Throwable) {
            // Ignore errors
        }

        return $relations;
    }

    /**
     * Check if an action is a custom action (not standard CRUD).
     */
    protected function isCustomAction(string $action): bool
    {
        $standardActions = ['view', 'edit', 'delete', 'replicate'];

        return ! in_array(Str::lower($action), array_map('strtolower', $standardActions));
    }

    /**
     * Extract metadata from the resource class.
     *
     * @return array<string, mixed>
     */
    protected function extractMetadata(string $resourceClass): array
    {
        $metadata = [
            'hasRelations' => false,
            'hasBulkActions' => false,
            'hasCustomActions' => false,
            'isGlobalSearch' => false,
        ];

        try {
            /** @var resource $resource */
            $resource = app($resourceClass);

            $metadata['hasRelations'] = count($resource::getRelations()) > 0;
            $metadata['hasBulkActions'] = count($this->detectBulkActions($resourceClass)) > 0;
            $metadata['hasCustomActions'] = count(array_filter(
                $this->detectTableActions($resourceClass),
                fn ($action) => $this->isCustomAction($action)
            )) > 0;

            // Check for global search
            $metadata['isGlobalSearch'] = method_exists($resource, 'getGloballySearchableAttributes')
                && ! empty($resource::getGloballySearchableAttributes());
        } catch (Throwable) {
            // Ignore errors
        }

        return $metadata;
    }
}
