<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\ValueObjects;

use Illuminate\Support\Str;

readonly class DiscoveredPage
{
    /**
     * @param  array<string>  $permissions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $fqcn,
        public ?string $title = null,
        public ?string $slug = null,
        public ?string $cluster = null,
        public array $permissions = [],
        public array $metadata = [],
        public ?string $panel = null,
    ) {}

    /**
     * Get the permission key for this page.
     */
    public function getPermissionKey(): string
    {
        $slug = $this->slug ?? Str::kebab(class_basename($this->fqcn));

        return "page.{$slug}";
    }

    /**
     * Get the page basename.
     */
    public function getBasename(): string
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
            'basename' => $this->getBasename(),
            'title' => $this->title,
            'slug' => $this->slug,
            'cluster' => $this->cluster,
            'permission' => $this->getPermissionKey(),
            'permissions' => $this->permissions,
            'panel' => $this->panel,
            'metadata' => $this->metadata,
        ];
    }
}
