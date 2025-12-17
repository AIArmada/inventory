<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use AIArmada\FilamentAuthz\Support\UserModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $created_by
 * @property array<string, mixed> $state
 * @property string $hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $creator
 */
class PermissionSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'state',
        'hash',
    ];

    public function getTable(): string
    {
        return config('filament-authz.database.tables.permission_snapshots', 'authz_permission_snapshots');
    }

    /**
     * Get the user who created this snapshot.
     */
    public function creator(): BelongsTo
    {
        $userModel = UserModelResolver::resolve();

        return $this->belongsTo($userModel, 'created_by');
    }

    /**
     * Get the roles from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoles(): array
    {
        return $this->state['roles'] ?? [];
    }

    /**
     * Get the permissions from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissions(): array
    {
        return $this->state['permissions'] ?? [];
    }

    /**
     * Get the assignments from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignments(): array
    {
        return $this->state['assignments'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
