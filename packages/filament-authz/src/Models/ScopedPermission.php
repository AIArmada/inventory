<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Support\UserModelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * @property string $id
 * @property string $permission_id
 * @property string $permissionable_type
 * @property string $permissionable_id
 * @property string $scope_type
 * @property string|null $scope_id
 * @property string|null $scope_value
 * @property string|null $scope_model
 * @property array<string, mixed>|null $conditions
 * @property Carbon $granted_at
 * @property Carbon|null $expires_at
 * @property string|null $granted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Permission $permission
 * @property-read Model $permissionable
 * @property-read Model|null $granter
 */
class ScopedPermission extends Model
{
    use HasUuids;

    protected $fillable = [
        'permission_id',
        'permissionable_type',
        'permissionable_id',
        'scope_type',
        'scope_id',
        'scope_value',
        'scope_model',
        'conditions',
        'granted_at',
        'expires_at',
        'granted_by',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('filament-authz.database.tables.scoped_permissions', 'authz_scoped_permissions');

        return $table;
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function permissionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function granter(): BelongsTo
    {
        $userModel = UserModelResolver::resolve();

        return $this->belongsTo($userModel, 'granted_by');
    }

    /**
     * Get the scope enum value.
     */
    public function getScopeEnum(): PermissionScope
    {
        return PermissionScope::tryFrom($this->scope_type) ?? PermissionScope::Global;
    }

    /**
     * Check if this scoped permission has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if this scoped permission is active (not expired).
     */
    public function isActive(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Check if this scoped permission is within a specific scope context.
     *
     * @param  array<string, mixed>  $context
     */
    public function matchesContext(array $context): bool
    {
        $scope = $this->getScopeEnum();

        // Global scope matches everything
        if ($scope === PermissionScope::Global) {
            return true;
        }

        // Check scope-specific context
        $contextKey = match ($scope) {
            PermissionScope::Team => 'team_id',
            PermissionScope::Tenant => 'tenant_id',
            PermissionScope::Resource => 'resource_id',
            PermissionScope::Owner => 'owner_id',
            default => null,
        };

        if ($contextKey === null) {
            return true;
        }

        $contextValue = $context[$contextKey] ?? null;

        if ($contextValue === null) {
            return false;
        }

        return $this->scope_id === (string) $contextValue;
    }

    /**
     * Check if this scoped permission's conditions are satisfied.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function evaluateConditions(array $attributes): bool
    {
        $conditions = $this->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $attributes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for evaluateConditions for backward compatibility.
     *
     * @param  array<string, mixed>  $context
     */
    public function matchesConditions(array $context): bool
    {
        return $this->evaluateConditions($context);
    }

    /**
     * Scope to filter active (non-expired) permissions.
     *
     * @param  Builder<ScopedPermission>  $query
     * @return Builder<ScopedPermission>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to filter expired permissions.
     *
     * @param  Builder<ScopedPermission>  $query
     * @return Builder<ScopedPermission>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope to filter by scope type.
     *
     * @param  Builder<ScopedPermission>  $query
     * @return Builder<ScopedPermission>
     */
    public function scopeOfType(Builder $query, PermissionScope | string $scopeType): Builder
    {
        $type = $scopeType instanceof PermissionScope ? $scopeType->value : $scopeType;

        return $query->where('scope_type', $type);
    }

    /**
     * Scope to filter by scope context.
     *
     * @param  Builder<ScopedPermission>  $query
     * @return Builder<ScopedPermission>
     */
    public function scopeForScope(Builder $query, PermissionScope | string $scopeType, string $scopeId): Builder
    {
        $type = $scopeType instanceof PermissionScope ? $scopeType->value : $scopeType;

        return $query->where('scope_type', $type)->where('scope_id', $scopeId);
    }

    /**
     * Scope to filter by permissionable.
     *
     * @param  Builder<ScopedPermission>  $query
     * @return Builder<ScopedPermission>
     */
    public function scopeForPermissionable(Builder $query, Model $model): Builder
    {
        return $query
            ->where('permissionable_type', $model->getMorphClass())
            ->where('permissionable_id', $model->getKey());
    }

    /**
     * Scope to filter expiring within a given period.
     *
     * @param  Builder<ScopedPermission>  $query
     * @return Builder<ScopedPermission>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days));
    }

    /**
     * Evaluate a single condition.
     *
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $attributes
     */
    protected function evaluateCondition(array $condition, array $attributes): bool
    {
        $attribute = $condition['attribute'] ?? null;
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;
        $source = $condition['source'] ?? 'subject';

        if ($attribute === null) {
            return true;
        }

        $attributeValue = $attributes[$attribute] ?? null;

        return match ($operator) {
            'eq' => $attributeValue === $value,
            'neq' => $attributeValue !== $value,
            'gt' => $attributeValue > $value,
            'gte' => $attributeValue >= $value,
            'lt' => $attributeValue < $value,
            'lte' => $attributeValue <= $value,
            'in' => is_array($value) && in_array($attributeValue, $value, true),
            'not_in' => is_array($value) && ! in_array($attributeValue, $value, true),
            'contains' => is_string($attributeValue) && str_contains($attributeValue, (string) $value),
            'is_null' => $attributeValue === null,
            'is_not_null' => $attributeValue !== null,
            default => true,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
