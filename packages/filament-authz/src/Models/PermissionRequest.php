<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $requester_id
 * @property string|null $approver_id
 * @property array<string>|null $requested_permissions
 * @property array<string>|null $requested_roles
 * @property string|null $justification
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $denied_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $approver_note
 * @property string|null $denial_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $requester
 * @property-read Model|null $approver
 */
class PermissionRequest extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'requester_id',
        'approver_id',
        'requested_permissions',
        'requested_roles',
        'justification',
        'status',
        'approved_at',
        'denied_at',
        'expires_at',
        'approver_note',
        'denial_reason',
    ];

    public function getTable(): string
    {
        return config('filament-authz.database.tables.permission_requests', 'authz_permission_requests');
    }

    /**
     * Get the user who requested the permission.
     */
    public function requester(): BelongsTo
    {
        $userModel = config('filament-authz.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'requester_id');
    }

    /**
     * Get the user who approved/denied the request.
     */
    public function approver(): BelongsTo
    {
        $userModel = config('filament-authz.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'approver_id');
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the request is denied.
     */
    public function isDenied(): bool
    {
        return $this->status === self::STATUS_DENIED;
    }

    /**
     * Check if the request has expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Approve the permission request.
     */
    public function approve(mixed $approver, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approver_id' => $approver->id,
            'approved_at' => now(),
            'approver_note' => $note,
        ]);

        // Apply permissions
        foreach ($this->requested_permissions ?? [] as $permission) {
            if (method_exists($this->requester, 'givePermissionTo')) {
                $this->requester->givePermissionTo($permission);
            }
        }

        // Apply roles
        foreach ($this->requested_roles ?? [] as $role) {
            if (method_exists($this->requester, 'assignRole')) {
                $this->requester->assignRole($role);
            }
        }
    }

    /**
     * Deny the permission request.
     */
    public function deny(mixed $approver, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_DENIED,
            'approver_id' => $approver->id,
            'denied_at' => now(),
            'denial_reason' => $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'requested_permissions' => 'array',
            'requested_roles' => 'array',
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
