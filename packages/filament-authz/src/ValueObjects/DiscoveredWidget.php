<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\ValueObjects;

use Illuminate\Support\Str;

readonly class DiscoveredWidget
{
    /**
     * @param  array<string>  $permissions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $fqcn,
        public ?string $name = null,
        public ?string $type = null,
        public array $permissions = [],
        public array $metadata = [],
        public ?string $panel = null,
    ) {}

    /**
     * Get the permission key for this widget.
     */
    public function getPermissionKey(): string
    {
        $name = $this->name ?? Str::snake(class_basename($this->fqcn));

        return "widget.{$name}";
    }

    /**
     * Get the widget basename.
     */
    public function getBasename(): string
    {
        return class_basename($this->fqcn);
    }

    /**
     * Check if this is a chart widget.
     */
    public function isChart(): bool
    {
        return $this->metadata['isChart'] ?? false;
    }

    /**
     * Check if this is a stats overview widget.
     */
    public function isStats(): bool
    {
        return $this->metadata['isStats'] ?? false;
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
            'name' => $this->name ?? $this->getBasename(),
            'type' => $this->type,
            'permission' => $this->getPermissionKey(),
            'permissions' => $this->permissions,
            'panel' => $this->panel,
            'metadata' => $this->metadata,
        ];
    }
}
