<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Database\Factories\InventoryAllocationFactory;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string $location_id
 * @property string $level_id
 * @property string|null $batch_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string $cart_id
 * @property int $quantity
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InventoryLocation $location
 * @property-read InventoryLevel $level
 * @property-read Model $inventoryable
 */
final class InventoryAllocation extends Model
{
    /** @use HasFactory<InventoryAllocationFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'level_id',
        'batch_id',
        'owner_type',
        'owner_id',
        'cart_id',
        'quantity',
        'expires_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.allocations', 'inventory_allocations');
    }

    /**
     * Get the inventoryable model (Product, Variant, etc.)
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the location for this allocation.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get the inventory level for this allocation.
     *
     * @return BelongsTo<InventoryLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(InventoryLevel::class, 'level_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * Check if the allocation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the allocation is still active (not expired).
     */
    public function isActive(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Extend the allocation expiry.
     */
    public function extend(int $minutes): self
    {
        $this->update([
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return $this;
    }

    /**
     * Scope to filter by cart ID.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCart(Builder $query, string $cartId): Builder
    {
        return $query->where('cart_id', $cartId);
    }

    /**
     * Scope to filter active (non-expired) allocations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to filter expired allocations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to filter by location.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAtLocation(Builder $query, string $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InventoryAllocationFactory
    {
        return InventoryAllocationFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryAllocation $allocation): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $location = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($allocation->location_id)
                ->first();

            if ($location === null) {
                throw new AuthorizationException('Invalid location for current owner context.');
            }

            $level = InventoryLevel::query()
                ->whereKey($allocation->level_id)
                ->first();

            if ($level === null) {
                throw new AuthorizationException('Invalid inventory level for current owner context.');
            }

            if ($level->location_id !== $allocation->location_id) {
                throw new AuthorizationException('Inventory allocation location does not match inventory level location.');
            }

            if ($allocation->batch_id !== null) {
                $batch = InventoryBatch::query()
                    ->whereKey($allocation->batch_id)
                    ->first();

                if ($batch === null) {
                    throw new AuthorizationException('Invalid inventory batch for current owner context.');
                }

                if ($batch->location_id !== $allocation->location_id) {
                    throw new AuthorizationException('Inventory allocation batch does not match allocation location.');
                }
            }

            if (($allocation->owner_type !== null || $allocation->owner_id !== null)
                && ($allocation->owner_type !== $location->owner_type || $allocation->owner_id !== $location->owner_id)) {
                throw new AuthorizationException('Owner mismatch between inventory allocation and location.');
            }

            $allocation->owner_type = $location->owner_type;
            $allocation->owner_id = $location->owner_id;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
