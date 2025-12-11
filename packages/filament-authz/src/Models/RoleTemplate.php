<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $parent_id
 * @property string $guard_name
 * @property array<string>|null $default_permissions
 * @property array<string, mixed>|null $metadata
 * @property bool $is_system
 * @property bool $is_active
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RoleTemplate|null $parent
 * @property-read Collection<int, RoleTemplate> $children
 * @property-read Collection<int, Role> $roles
 */
class RoleTemplate extends Model
{
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'guard_name',
        'default_permissions',
        'metadata',
        'is_system',
        'is_active',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('filament-authz.database.tables.role_templates', 'authz_role_templates');

        return $table;
    }

    /**
     * @return BelongsTo<RoleTemplate, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<RoleTemplate, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get roles using this template.
     *
     * @return HasMany<Model, $this>
     */
    public function roles(): HasMany
    {
        /** @var class-string<Model> $roleClass */
        $roleClass = config('permission.models.role', Role::class);

        return $this->hasMany($roleClass, 'template_id');
    }

    /**
     * Get all permissions including those inherited from parent templates.
     *
     * @return array<string>
     */
    public function getAllDefaultPermissions(): array
    {
        $permissions = $this->default_permissions ?? [];

        if ($this->parent) {
            $parentPermissions = $this->parent->getAllDefaultPermissions();
            $permissions = array_unique(array_merge($parentPermissions, $permissions));
        }

        return $permissions;
    }

    /**
     * Get all ancestor templates.
     *
     * @return Collection<int, RoleTemplate>
     */
    public function getAncestors(): Collection
    {
        $ancestors = new Collection;
        $current = $this->parent;

        while ($current !== null) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendant templates.
     *
     * @return Collection<int, RoleTemplate>
     */
    public function getDescendants(): Collection
    {
        $descendants = new Collection;

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Create a new role from this template.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function createRole(string $name, array $overrides = []): Role
    {
        $role = Role::create(array_merge([
            'name' => $name,
            'guard_name' => $this->guard_name,
            'template_id' => $this->id,
            'description' => $this->description,
        ], $overrides));

        $permissions = $this->getAllDefaultPermissions();
        if (! empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    /**
     * Sync an existing role with this template's permissions.
     */
    public function syncRole(Role $role): Role
    {
        $permissions = $this->getAllDefaultPermissions();
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Check if this template is an ancestor of another template.
     */
    public function isAncestorOf(self $template): bool
    {
        return $template->getAncestors()->contains('id', $this->id);
    }

    /**
     * Check if this template is a descendant of another template.
     */
    public function isDescendantOf(self $template): bool
    {
        return $this->getAncestors()->contains('id', $template->id);
    }

    /**
     * Get the depth of this template in the hierarchy.
     */
    public function getDepth(): int
    {
        return $this->getAncestors()->count();
    }

    /**
     * Check if this template is a root template (no parent).
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('filament-authz.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    protected static function booted(): void
    {
        static::deleting(function (RoleTemplate $template): void {
            // Reassign children to parent
            self::query()
                ->where('parent_id', $template->id)
                ->update(['parent_id' => $template->parent_id]);

            // Disassociate roles from this template
            Role::query()
                ->where('template_id', $template->id)
                ->update(['template_id' => null]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_permissions' => 'array',
            'metadata' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
