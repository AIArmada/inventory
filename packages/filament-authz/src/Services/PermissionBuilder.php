<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Support\Str;

class PermissionBuilder
{
    protected string $resource;

    /**
     * @var array<string>
     */
    protected array $abilities = [];

    protected ?string $group = null;

    protected ?string $guardName = null;

    /**
     * @var array<string, string>
     */
    protected array $descriptions = [];

    /**
     * Start building permissions for a resource.
     */
    public static function for(string $resource): self
    {
        $builder = new self;
        $builder->resource = $resource;

        return $builder;
    }

    /**
     * Add standard CRUD abilities.
     */
    public function crud(): self
    {
        return $this->abilities(['viewAny', 'view', 'create', 'update', 'delete']);
    }

    /**
     * Add full CRUD.
     */
    public function fullCrud(): self
    {
        return $this->crud();
    }

    /**
     * Add specific abilities.
     *
     * @param  array<string>  $abilities
     */
    public function abilities(array $abilities): self
    {
        $this->abilities = array_merge($this->abilities, $abilities);

        return $this;
    }

    /**
     * Add a single ability.
     */
    public function ability(string $ability, ?string $description = null): self
    {
        $this->abilities[] = $ability;

        if ($description !== null) {
            $this->descriptions[$ability] = $description;
        }

        return $this;
    }

    /**
     * Add view-only abilities.
     */
    public function viewOnly(): self
    {
        return $this->abilities(['viewAny', 'view']);
    }

    /**
     * Add manage ability (implies all CRUD).
     */
    public function manage(): self
    {
        return $this->ability('manage', 'Full management access');
    }

    /**
     * Add a wildcard permission.
     */
    public function wildcard(): self
    {
        return $this->ability('*', 'All permissions');
    }

    /**
     * Add export ability.
     */
    public function export(): self
    {
        return $this->ability('export', 'Export data');
    }

    /**
     * Add import ability.
     */
    public function import(): self
    {
        return $this->ability('import', 'Import data');
    }

    /**
     * Add replicate ability.
     */
    public function replicate(): self
    {
        return $this->ability('replicate', 'Duplicate records');
    }

    /**
     * Add bulk action abilities.
     */
    public function bulkActions(): self
    {
        return $this->abilities(['bulkDelete', 'bulkUpdate']);
    }

    /**
     * Set the permission group.
     */
    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Set the guard name.
     */
    public function guard(string $guardName): self
    {
        $this->guardName = $guardName;

        return $this;
    }

    /**
     * Set descriptions for abilities.
     *
     * @param  array<string, string>  $descriptions
     */
    public function describe(array $descriptions): self
    {
        $this->descriptions = array_merge($this->descriptions, $descriptions);

        return $this;
    }

    /**
     * Build the permission definitions.
     *
     * @return array<string, array{name: string, description: string|null, group: string|null, resource: string, guard_name: string|null}>
     */
    public function build(): array
    {
        $permissions = [];
        $resourceLabel = Str::headline($this->resource);

        foreach (array_unique($this->abilities) as $ability) {
            $name = "{$this->resource}.{$ability}";

            $description = $this->descriptions[$ability]
                ?? $this->generateDescription($ability, $resourceLabel);

            $permissions[$name] = [
                'name' => $name,
                'description' => $description,
                'group' => $this->group,
                'resource' => $this->resource,
                'guard_name' => $this->guardName,
            ];
        }

        return $permissions;
    }

    /**
     * Build and register with the registry.
     *
     * @return array<string, array{name: string, description: string|null, group: string|null, resource: string, guard_name: string|null}>
     */
    public function register(?PermissionRegistry $registry = null): array
    {
        $registry = $registry ?? app(PermissionRegistry::class);
        $permissions = $this->build();

        foreach ($permissions as $definition) {
            $registry->register(
                $definition['name'],
                $definition['description'],
                $definition['group'],
                $definition['resource']
            );
        }

        return $permissions;
    }

    /**
     * Get permission names only.
     *
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return array_map(
            fn (string $ability) => "{$this->resource}.{$ability}",
            array_unique($this->abilities)
        );
    }

    /**
     * Generate a description for an ability.
     */
    protected function generateDescription(string $ability, string $resourceLabel): string
    {
        return match ($ability) {
            'viewAny' => "View any {$resourceLabel}",
            'view' => "View {$resourceLabel}",
            'create' => "Create {$resourceLabel}",
            'update' => "Update {$resourceLabel}",
            'delete' => "Delete {$resourceLabel}",
            'replicate' => "Duplicate {$resourceLabel}",
            'export' => "Export {$resourceLabel}",
            'import' => "Import {$resourceLabel}",
            'bulkDelete' => "Bulk delete {$resourceLabel}",
            'bulkUpdate' => "Bulk update {$resourceLabel}",
            'manage' => "Full management of {$resourceLabel}",
            '*' => "All {$resourceLabel} permissions",
            default => Str::headline($ability) . " {$resourceLabel}",
        };
    }
}
