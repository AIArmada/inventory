<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Durable reservation-group aggregate for checkout references.
 *
 * Owns zero or more split allocation rows and retains terminal status
 * after allocations are deleted. Identity is (owner_type, owner_id, reference).
 *
 * @property string $id
 * @property string $reference
 * @property string $state
 * @property array<string, array{requested: int, reserved: int}> $line_snapshot
 * @property string|null $owner_type
 * @property string|int|null $owner_id
 * @property string|null $order_id
 * @property int $ttl_seconds
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryAllocation> $allocations
 */
final class InventoryReservation extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    public const string STATE_RESERVED = 'reserved';

    public const string STATE_COMMITTED = 'committed';

    public const string STATE_RELEASED = 'released';

    public const string STATE_EXPIRED = 'expired';

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'reference',
        'state',
        'line_snapshot',
        'order_id',
        'ttl_seconds',
        'expires_at',
        'owner_id',
        'owner_type',
    ];

    protected $casts = [
        'ttl_seconds' => 'integer',
        'line_snapshot' => 'array',
        'expires_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('inventory.database.tables.reservations', 'inventory_reservations');
    }

    /** @return HasMany<InventoryAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'reservation_group_id');
    }

    public function isActive(): bool
    {
        return $this->state === self::STATE_RESERVED && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->state === self::STATE_EXPIRED
            || ($this->state === self::STATE_RESERVED && $this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [self::STATE_COMMITTED, self::STATE_RELEASED, self::STATE_EXPIRED], true);
    }
}
