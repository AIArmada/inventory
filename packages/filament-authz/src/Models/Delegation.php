<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $delegator_id
 * @property string $delegatee_id
 * @property string $permission
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $can_redelegate
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $delegator
 * @property-read Model $delegatee
 */
class Delegation extends Model
{
    use HasUuids;

    protected $fillable = [
        'delegator_id',
        'delegatee_id',
        'permission',
        'expires_at',
        'can_redelegate',
        'revoked_at',
    ];

    public function getTable(): string
    {
        return config('filament-authz.database.tables.delegations', 'authz_delegations');
    }

    /**
     * Get the user who delegated the permission.
     */
    public function delegator(): BelongsTo
    {
        $userModel = config('filament-authz.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'delegator_id');
    }

    /**
     * Get the user who received the delegation.
     */
    public function delegatee(): BelongsTo
    {
        $userModel = config('filament-authz.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'delegatee_id');
    }

    /**
     * Check if the delegation is active.
     */
    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the delegation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the delegation has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Revoke the delegation.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'can_redelegate' => 'boolean',
        ];
    }
}
