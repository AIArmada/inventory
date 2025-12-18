<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

class TeamPermissionService
{
    public function __construct(
        protected ContextualAuthorizationService $contextualAuth
    ) {}

    /**
     * Check if a user has a permission within a team.
     *
     * @param  object  $user
     * @param  string|int  $teamId
     */
    public function hasTeamPermission($user, string $permission, $teamId): bool
    {
        return $this->contextualAuth->canInTeam($user, $permission, $teamId);
    }

    /**
     * Grant a permission to a user within a team.
     *
     * @param  object  $user
     * @param  string|int  $teamId
     * @param  array<string, mixed>  $conditions
     */
    public function grantTeamPermission(
        $user,
        string $permission,
        $teamId,
        array $conditions = [],
        ?DateTimeInterface $expiresAt = null
    ): ScopedPermission {
        return $this->contextualAuth->grantScopedPermission(
            user: $user,
            permission: $permission,
            scope: PermissionScope::Team,
            scopeValue: (string) $teamId,
            conditions: $conditions,
            expiresAt: $expiresAt
        );
    }

    /**
     * Revoke a permission from a user within a team.
     *
     * @param  object  $user
     * @param  string|int  $teamId
     */
    public function revokeTeamPermission($user, string $permission, $teamId): int
    {
        return $this->contextualAuth->revokeScopedPermission(
            user: $user,
            permission: $permission,
            scope: PermissionScope::Team,
            scopeValue: (string) $teamId
        );
    }

    /**
     * Get all permissions a user has within a team.
     *
     * @param  object  $user
     * @param  string|int  $teamId
     * @return Collection<int, ScopedPermission>
     */
    public function getTeamPermissions($user, $teamId): Collection
    {
        return ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->where('scope_type', PermissionScope::Team)
            ->where('scope_id', (string) $teamId)
            ->active()
            ->with('permission')
            ->get();
    }

    /**
     * Get all teams where a user has a specific permission.
     *
     * @param  object  $user
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getTeamsWithPermission($user, string $permission): \Illuminate\Support\Collection
    {
        return ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->where('scope_type', PermissionScope::Team)
            ->whereHas('permission', fn ($q) => $q->where('name', $permission))
            ->active()
            ->pluck('scope_id');
    }

    /**
     * Revoke all permissions from a user within a team.
     *
     * @param  object  $user
     * @param  string|int  $teamId
     */
    public function revokeAllTeamPermissions($user, $teamId): int
    {
        return ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->where('scope_type', PermissionScope::Team)
            ->where('scope_id', (string) $teamId)
            ->delete();
    }

    /**
     * Copy permissions from one team to another.
     *
     * @param  object  $user
     * @param  string|int  $fromTeamId
     * @param  string|int  $toTeamId
     */
    public function copyTeamPermissions($user, $fromTeamId, $toTeamId): int
    {
        $permissions = $this->getTeamPermissions($user, $fromTeamId);
        $count = 0;

        foreach ($permissions as $scopedPermission) {
            $this->grantTeamPermission(
                user: $user,
                permission: $scopedPermission->permission->name,
                teamId: $toTeamId,
                conditions: $scopedPermission->conditions ?? [],
                expiresAt: $scopedPermission->expires_at
            );
            $count++;
        }

        return $count;
    }
}
