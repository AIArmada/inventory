<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionVersioningService
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Create a snapshot of current permission state.
     */
    public function createSnapshot(string $name, ?string $description = null): PermissionSnapshot
    {
        $state = [
            'roles' => $this->serializeRoles(),
            'permissions' => $this->serializePermissions(),
            'assignments' => $this->serializeAssignments(),
        ];

        $snapshot = PermissionSnapshot::create([
            'name' => $name,
            'description' => $description,
            'created_by' => auth()->id(),
            'state' => $state,
            'hash' => $this->calculateStateHash($state),
        ]);

        $this->auditLogger->log(
            eventType: AuditEventType::SnapshotCreated,
            metadata: [
                'snapshot_id' => $snapshot->id,
                'name' => $name,
            ]
        );

        return $snapshot;
    }

    /**
     * Compare two snapshots and return the differences.
     *
     * @return array<string, array<string, array<int, mixed>>>
     */
    public function compare(PermissionSnapshot $from, PermissionSnapshot $to): array
    {
        $fromRoles = collect($from->getRoles())->pluck('name')->toArray();
        $toRoles = collect($to->getRoles())->pluck('name')->toArray();

        $fromPermissions = collect($from->getPermissions())->pluck('name')->toArray();
        $toPermissions = collect($to->getPermissions())->pluck('name')->toArray();

        return [
            'roles' => [
                'added' => array_values(array_diff($toRoles, $fromRoles)),
                'removed' => array_values(array_diff($fromRoles, $toRoles)),
            ],
            'permissions' => [
                'added' => array_values(array_diff($toPermissions, $fromPermissions)),
                'removed' => array_values(array_diff($fromPermissions, $toPermissions)),
            ],
            'assignments_changed' => $this->diffAssignments($from, $to),
        ];
    }

    /**
     * Preview what would happen if we rollback to a snapshot.
     *
     * @return array<string, array<string, array<int, mixed>>>
     */
    public function previewRollback(PermissionSnapshot $snapshot): array
    {
        $current = $this->createTemporarySnapshot();

        return $this->compare($current, $snapshot);
    }

    /**
     * Rollback to a previous snapshot.
     */
    public function rollback(PermissionSnapshot $snapshot, bool $dryRun = false): RollbackResult
    {
        if ($dryRun) {
            return new RollbackResult(
                success: true,
                snapshot: $snapshot,
                preview: $this->previewRollback($snapshot),
                isDryRun: true
            );
        }

        DB::transaction(function () use ($snapshot): void {
            // Clear current state
            DB::table('role_has_permissions')->delete();
            DB::table('model_has_roles')->delete();
            DB::table('model_has_permissions')->delete();
            DB::table('roles')->delete();
            DB::table('permissions')->delete();

            // Restore from snapshot
            foreach ($snapshot->getRoles() as $roleData) {
                Role::create($roleData);
            }

            foreach ($snapshot->getPermissions() as $permData) {
                Permission::create($permData);
            }

            foreach ($snapshot->getAssignments() as $assignment) {
                $this->restoreAssignment($assignment);
            }
        });

        // Clear cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->auditLogger->log(
            eventType: AuditEventType::SnapshotRestored,
            metadata: [
                'snapshot_id' => $snapshot->id,
                'name' => $snapshot->name,
            ]
        );

        return new RollbackResult(
            success: true,
            snapshot: $snapshot,
            restoredAt: now(),
            isDryRun: false
        );
    }

    /**
     * Get all snapshots.
     *
     * @return Collection<int, PermissionSnapshot>
     */
    public function listSnapshots(): Collection
    {
        return PermissionSnapshot::orderBy('created_at', 'desc')->get();
    }

    /**
     * Delete a snapshot.
     */
    public function deleteSnapshot(PermissionSnapshot $snapshot): bool
    {
        return $snapshot->delete();
    }

    /**
     * Serialize all roles.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeRoles(): array
    {
        return Role::all()->map(function (Role $role) {
            return [
                'name' => $role->name,
                'guard_name' => $role->guard_name,
            ];
        })->toArray();
    }

    /**
     * Serialize all permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializePermissions(): array
    {
        return Permission::all()->map(function (Permission $permission) {
            return [
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ];
        })->toArray();
    }

    /**
     * Serialize all role and permission assignments.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeAssignments(): array
    {
        $assignments = [];

        // Role-permission assignments
        $rolePermissions = DB::table('role_has_permissions')
            ->join('roles', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->select('roles.name as role', 'permissions.name as permission')
            ->get();

        foreach ($rolePermissions as $rp) {
            $assignments[] = [
                'type' => 'role_permission',
                'role' => $rp->role,
                'permission' => $rp->permission,
            ];
        }

        // Model-role assignments
        $modelRoles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('model_has_roles.model_type', 'model_has_roles.model_id', 'roles.name as role')
            ->get();

        foreach ($modelRoles as $mr) {
            $assignments[] = [
                'type' => 'model_role',
                'model_type' => $mr->model_type,
                'model_id' => $mr->model_id,
                'role' => $mr->role,
            ];
        }

        return $assignments;
    }

    /**
     * Restore an assignment from snapshot data.
     *
     * @param  array<string, mixed>  $assignment
     */
    protected function restoreAssignment(array $assignment): void
    {
        if ($assignment['type'] === 'role_permission') {
            $role = Role::findByName($assignment['role']);
            $permission = Permission::findByName($assignment['permission']);
            if ($role !== null && $permission !== null) {
                $role->givePermissionTo($permission);
            }
        }

        if ($assignment['type'] === 'model_role') {
            $model = $assignment['model_type']::find($assignment['model_id']);
            if ($model !== null && method_exists($model, 'assignRole')) {
                $model->assignRole($assignment['role']);
            }
        }
    }

    /**
     * Calculate hash of the state for comparison.
     *
     * @param  array<string, mixed>  $state
     */
    protected function calculateStateHash(array $state): string
    {
        return md5(json_encode($state) ?: '');
    }

    /**
     * Create a temporary snapshot for comparison without persisting.
     */
    protected function createTemporarySnapshot(): PermissionSnapshot
    {
        $state = [
            'roles' => $this->serializeRoles(),
            'permissions' => $this->serializePermissions(),
            'assignments' => $this->serializeAssignments(),
        ];

        $snapshot = new PermissionSnapshot();
        $snapshot->state = $state;
        $snapshot->hash = $this->calculateStateHash($state);

        return $snapshot;
    }

    /**
     * Diff assignments between two snapshots.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function diffAssignments(PermissionSnapshot $from, PermissionSnapshot $to): array
    {
        $fromAssignments = collect($from->getAssignments());
        $toAssignments = collect($to->getAssignments());

        $fromKeys = $fromAssignments->map(fn ($a) => json_encode($a))->toArray();
        $toKeys = $toAssignments->map(fn ($a) => json_encode($a))->toArray();

        $addedKeys = array_diff($toKeys, $fromKeys);
        $removedKeys = array_diff($fromKeys, $toKeys);

        return [
            'added' => collect($addedKeys)->map(fn ($k) => json_decode($k, true))->values()->toArray(),
            'removed' => collect($removedKeys)->map(fn ($k) => json_decode($k, true))->values()->toArray(),
        ];
    }
}

/**
 * Value object representing a rollback result.
 */
readonly class RollbackResult
{
    /**
     * @param  array<string, mixed>|null  $preview
     */
    public function __construct(
        public bool $success,
        public PermissionSnapshot $snapshot,
        public ?array $preview = null,
        public ?DateTimeInterface $restoredAt = null,
        public bool $isDryRun = false,
    ) {}
}
