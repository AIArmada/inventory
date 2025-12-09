<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\ValueObjects;

use Illuminate\Support\Str;

readonly class DiscoveredResource
{
    /**
     * @param  array<string>  $permissions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $fqcn,
        public string $model,
        public array $permissions,
        public array $metadata,
        public ?string $panel = null,
        public ?string $navigationGroup = null,
        public ?string $navigationLabel = null,
        public ?string $slug = null,
        public ?string $cluster = null,
    ) {}

    /**
     * Convert to permission keys with configurable separator.
     *
     * @return array<string>
     */
    public function toPermissionKeys(string $separator = '.'): array
    {
        $subject = Str::snake(class_basename($this->model));

        return collect($this->permissions)
            ->map(fn (string $perm): string => "{$subject}{$separator}{$perm}")
            ->toArray();
    }

    /**
     * Check if model has an existing policy.
     */
    public function hasExistingPolicy(): bool
    {
        return class_exists($this->getPolicyClass());
    }

    /**
     * Get the policy class for this resource's model.
     */
    public function getPolicyClass(): string
    {
        return Str::of($this->model)
            ->replace('Models', 'Policies')
            ->append('Policy')
            ->toString();
    }

    /**
     * Get the model basename.
     */
    public function getModelBasename(): string
    {
        return class_basename($this->model);
    }

    /**
     * Get the resource basename.
     */
    public function getResourceBasename(): string
    {
        return class_basename($this->fqcn);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'model' => $this->model,
            'model_basename' => $this->getModelBasename(),
            'permissions' => $this->permissions,
            'permission_keys' => $this->toPermissionKeys(),
            'panel' => $this->panel,
            'navigation_group' => $this->navigationGroup,
            'navigation_label' => $this->navigationLabel,
            'slug' => $this->slug,
            'cluster' => $this->cluster,
            'has_existing_policy' => $this->hasExistingPolicy(),
            'policy_class' => $this->getPolicyClass(),
            'metadata' => $this->metadata,
        ];
    }
}
