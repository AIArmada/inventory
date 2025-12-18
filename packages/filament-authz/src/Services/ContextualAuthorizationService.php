<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class ContextualAuthorizationService
{
    protected const CACHE_KEY_PREFIX = 'permissions:contextual:';

    public function __construct(
        protected PermissionAggregator $aggregator
    ) {}

    /**
     * Check if a user has a permission within a specific context.
     *
     * @param  object  $user
     * @param  array<string, mixed>  $context
     */
    public function canWithContext($user, string $permission, array $context = []): bool
    {
        // First check global permission
        if ($this->aggregator->userHasPermission($user, $permission)) {
            return true;
        }

        // Check scoped permissions
        $scopedPermissions = $this->getScopedPermissions($user, $permission);

        foreach ($scopedPermissions as $scopedPermission) {
            if ($this->matchesContext($scopedPermission, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has permission for a specific resource.
     *
     * @param  object  $user
     */
    public function canForResource($user, string $permission, Model $resource): bool
    {
        // Build context from resource
        $context = [
            'resource_type' => $resource::class,
            'resource_id' => $resource->getKey(),
        ];

        // Add owner check if resource has user_id
        if (method_exists($resource, 'getAttribute')) {
            if ($resource->getAttribute('user_id') !== null) {
                $context['owner_id'] = $resource->getAttribute('user_id');
            }

            if ($resource->getAttribute('team_id') !== null) {
                $context['team_id'] = $resource->getAttribute('team_id');
            }

            if ($resource->getAttribute('tenant_id') !== null) {
                $context['tenant_id'] = $resource->getAttribute('tenant_id');
            }
        }

        return $this->canWithContext($user, $permission, $context);
    }

    /**
     * Check if a user has permission within a team.
     *
     * @param  object  $user
     * @param  string|int  $teamId
     */
    public function canInTeam($user, string $permission, $teamId): bool
    {
        return $this->canWithContext($user, $permission, [
            'team_id' => $teamId,
            'scope' => PermissionScope::Team->value,
        ]);
    }

    /**
     * Check if a user has permission within a tenant.
     *
     * @param  object  $user
     * @param  string|int  $tenantId
     */
    public function canInTenant($user, string $permission, $tenantId): bool
    {
        return $this->canWithContext($user, $permission, [
            'tenant_id' => $tenantId,
            'scope' => PermissionScope::Tenant->value,
        ]);
    }

    /**
     * Grant a scoped permission.
     *
     * @param  object  $user
     * @param  array<string, mixed>  $conditions
     */
    public function grantScopedPermission(
        $user,
        string $permission,
        PermissionScope $scope,
        string $scopeValue,
        array $conditions = [],
        ?DateTimeInterface $expiresAt = null
    ): ScopedPermission {
        $permissionModel = Permission::findOrCreate($permission);

        return ScopedPermission::create([
            'permissionable_type' => $user::class,
            'permissionable_id' => $user->getKey(),
            'permission_id' => $permissionModel->id,
            'scope_type' => $scope,
            'scope_id' => $scopeValue,
            'conditions' => $conditions,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'granted_by' => Auth::id(),
        ]);
    }

    /**
     * Revoke a scoped permission.
     *
     * @param  object  $user
     */
    public function revokeScopedPermission(
        $user,
        string $permission,
        ?PermissionScope $scope = null,
        ?string $scopeValue = null
    ): int {
        $query = ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->whereHas('permission', fn ($q) => $q->where('name', $permission));

        if ($scope !== null) {
            $query->where('scope_type', $scope);
        }

        if ($scopeValue !== null) {
            $query->where('scope_id', $scopeValue);
        }

        return $query->delete();
    }

    /**
     * Get all scoped permissions for a user.
     *
     * @param  object  $user
     * @return Collection<int, ScopedPermission>
     */
    public function getScopedPermissions($user, ?string $permission = null): Collection
    {
        $query = ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->active()
            ->with('permission');

        if ($permission !== null) {
            $query->whereHas('permission', fn ($q) => $q->where('name', $permission));
        }

        return $query->get();
    }

    /**
     * Get effective scopes for a user's permission.
     *
     * @param  object  $user
     * @return \Illuminate\Support\Collection<int, array{scope: string, value: string|null}>
     */
    public function getPermissionScopes($user, string $permission): \Illuminate\Support\Collection
    {
        return $this->getScopedPermissions($user, $permission)
            ->map(fn (ScopedPermission $sp): array => [
                'scope' => $sp->scope_type,
                'value' => $sp->scope_value,
            ]);
    }

    /**
     * Clear the context cache for a user.
     *
     * @param  object  $user
     */
    public function clearCache($user): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . "user:{$user->getKey()}");
    }

    /**
     * Check if scoped permission matches the given context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function matchesContext(ScopedPermission $scopedPermission, array $context): bool
    {
        // Check scope match
        $scopeType = $scopedPermission->scope_type;
        $scopeId = $scopedPermission->scope_id;

        // Convert string scope_type to enum for comparison
        $scopeEnum = PermissionScope::tryFrom($scopeType);

        $scopeKey = match ($scopeEnum) {
            PermissionScope::Team => 'team_id',
            PermissionScope::Tenant => 'tenant_id',
            PermissionScope::Resource => 'resource_id',
            PermissionScope::Owner => 'owner_id',
            PermissionScope::Global => null,
            default => null,
        };

        if ($scopeKey !== null && isset($context[$scopeKey])) {
            if ((string) $context[$scopeKey] !== (string) $scopeId) {
                return false;
            }
        }

        // Check additional conditions
        return $scopedPermission->matchesConditions($context);
    }
}
