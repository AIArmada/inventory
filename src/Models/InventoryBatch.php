<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use AIArmada\Inventory\Database\Factories\InventoryBatchFactory;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string $batch_number
 * @property string|null $lot_number
 * @property string|null $supplier_batch_number
 * @property string $location_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property int $quantity_received
 * @property int $quantity_on_hand
 * @property int $quantity_reserved
 * @property Carbon|null $manufactured_at
 * @property Carbon $received_at
 * @property Carbon|null $expires_at
 * @property string $status
 * @property int|null $unit_cost_minor
 * @property string $currency
 * @property string|null $supplier_id
 * @property string|null $purchase_order_number
 * @property bool $is_quarantined
 * @property string|null $quarantine_reason
 * @property Carbon|null $quality_checked_at
 * @property string|null $quality_status
 * @property bool $is_recalled
 * @property string|null $recall_reason
 * @property Carbon|null $recalled_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $available
 * @property-read int $quantity
 * @property-read int $available_quantity
 * @property-read bool $is_expired
 * @property-read int|null $days_until_expiry
 * @property-read InventoryLocation $location
 * @property-read Model $inventoryable
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read Collection<int, InventoryAllocation> $allocations
 */
final class InventoryBatch extends Model
{
    /** @use HasFactory<InventoryBatchFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    /** @var list<string> */
    protected $hidden = [
        'owner_scope',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'batch_number',
        'lot_number',
        'supplier_batch_number',
        'location_id',
        'quantity_received',
        'quantity_on_hand',
        'quantity_reserved',
        'manufactured_at',
        'received_at',
        'expires_at',
        'status',
        'unit_cost_minor',
        'currency',
        'supplier_id',
        'purchase_order_number',
        'is_quarantined',
        'quarantine_reason',
        'quality_checked_at',
        'quality_status',
        'is_recalled',
        'recall_reason',
        'recalled_at',
        'metadata',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.database.tables.batches', 'inventory_batches');
    }

    /**
     * Get the inventoryable model.
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the location for this batch.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get movements for this batch.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }

    /**
     * Get allocations for this batch.
     *
     * @return HasMany<InventoryAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'batch_id');
    }

    /**
     * Get available quantity.
     */
    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * Check if batch is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }

    /**
     * Check if batch is expired (method form for Filament compatibility).
     */
    public function isExpired(): bool
    {
        return $this->is_expired;
    }

    /**
     * Get days until expiry (method form for Filament compatibility).
     */
    public function daysUntilExpiry(): ?int
    {
        return $this->days_until_expiry;
    }

    /**
     * Get the batch status as enum.
     */
    public function getStatusEnum(): BatchStatus
    {
        return BatchStatus::from($this->status);
    }

    /**
     * Check if batch can be allocated.
     */
    public function canAllocate(): bool
    {
        return $this->getStatusEnum()->isAllocatable()
            && ! $this->is_expired
            && $this->available > 0;
    }

    /**
     * Check if batch is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $daysUntil = $this->days_until_expiry;

        return $daysUntil !== null && $daysUntil <= $days && $daysUntil > 0;
    }

    /**
     * Quarantine the batch.
     */
    public function quarantine(string $reason): self
    {
        $this->update([
            'status' => BatchStatus::Quarantined->value,
            'is_quarantined' => true,
            'quarantine_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Release from quarantine.
     */
    public function releaseFromQuarantine(): self
    {
        $this->update([
            'status' => BatchStatus::Active->value,
            'is_quarantined' => false,
            'quarantine_reason' => null,
        ]);

        return $this;
    }

    /**
     * Recall the batch.
     */
    public function recall(string $reason): self
    {
        $this->update([
            'status' => BatchStatus::Recalled->value,
            'is_recalled' => true,
            'recall_reason' => $reason,
            'recalled_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as expired.
     */
    public function markExpired(): self
    {
        $this->update([
            'status' => BatchStatus::Expired->value,
        ]);

        return $this;
    }

    /**
     * Increment on-hand quantity.
     */
    public function incrementOnHand(int $quantity): self
    {
        $this->increment('quantity_on_hand', $quantity);

        return $this;
    }

    /**
     * Decrement on-hand quantity.
     */
    public function decrementOnHand(int $quantity): self
    {
        $this->decrement('quantity_on_hand', $quantity);

        if ($this->quantity_on_hand <= 0) {
            $this->update(['status' => BatchStatus::Depleted->value]);
        }

        return $this;
    }

    /**
     * Increment reserved quantity.
     */
    public function incrementReserved(int $quantity): self
    {
        $this->increment('quantity_reserved', $quantity);

        return $this;
    }

    /**
     * Decrement reserved quantity.
     */
    public function decrementReserved(int $quantity): self
    {
        $this->decrement('quantity_reserved', $quantity);

        return $this;
    }

    /**
     * Scope to filter by status.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, BatchStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope to filter active batches only.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BatchStatus::Active->value);
    }

    /**
     * Scope to filter allocatable batches.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAllocatable(Builder $query): Builder
    {
        return $query->where('status', BatchStatus::Active->value)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereRaw('(quantity_on_hand - quantity_reserved) > 0');
    }

    /**
     * Scope to filter expiring batches.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    /**
     * Scope to filter expired batches.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope to order by FEFO (First Expired, First Out).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('received_at');
    }

    /**
     * Scope to order by FIFO (First In, First Out).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('received_at');
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
     * Handle model lifecycle events.
     */
    protected static function booted(): void
    {
        static::saving(function (InventoryBatch $batch): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $location = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($batch->location_id)
                ->first();

            if ($location === null) {
                throw new AuthorizationException('Invalid location for current owner context.');
            }

            if (($batch->owner_type !== null || $batch->owner_id !== null)
                && ($batch->owner_type !== $location->owner_type || $batch->owner_id !== $location->owner_id)) {
                throw new AuthorizationException('Owner mismatch between inventory batch and location.');
            }

            $batch->owner_type = $location->owner_type;
            $batch->owner_id = $location->owner_id;
        });

        self::deleting(function (InventoryBatch $batch): void {
            $batch->allocations()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InventoryBatchFactory
    {
        return InventoryBatchFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'unit_cost_minor' => 'integer',
            'is_quarantined' => 'boolean',
            'is_recalled' => 'boolean',
            'manufactured_at' => 'date',
            'received_at' => 'date',
            'expires_at' => 'date',
            'quality_checked_at' => 'datetime',
            'recalled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
