<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Database\Factories\InventoryLevelFactory;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Enums\AllocationStrategy;
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
 * @property string $location_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property int $quantity_on_hand
 * @property int $quantity_reserved
 * @property float|null $quantity_on_hand_decimal
 * @property float|null $quantity_reserved_decimal
 * @property int|null $reorder_point
 * @property int|null $safety_stock
 * @property int|null $max_stock
 * @property string|null $alert_status
 * @property Carbon|null $last_alert_at
 * @property Carbon|null $last_stock_check_at
 * @property string $unit_of_measure
 * @property float $unit_conversion_factor
 * @property int|null $lead_time_days
 * @property string|null $preferred_supplier_id
 * @property string|null $allocation_strategy
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $available
 * @property-read int $current_quantity
 * @property-read int $quantity
 * @property-read int $quantity_available
 * @property-read int $sku_count
 * @property-read int $total_quantity
 * @property-read int $max_quantity
 * @property-read int $min_quantity
 * @property-read InventoryLocation $location
 * @property-read Model $inventoryable
 * @property-read Collection<int, InventoryAllocation> $allocations
 */
final class InventoryLevel extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventoryLevelFactory> */
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
        'owner_type',
        'owner_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_on_hand_decimal',
        'quantity_reserved_decimal',
        'reorder_point',
        'safety_stock',
        'max_stock',
        'alert_status',
        'last_alert_at',
        'last_stock_check_at',
        'unit_of_measure',
        'unit_conversion_factor',
        'lead_time_days',
        'preferred_supplier_id',
        'allocation_strategy',
        'metadata',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.levels', 'inventory_levels');
    }

    /**
     * Get the location for this inventory level.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get the inventoryable model (Product, Variant, etc.)
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get allocations for this inventory level.
     *
     * @return HasMany<InventoryAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'level_id');
    }

    /**
     * Get the available quantity (on_hand - reserved).
     */
    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * Alias for available (for compatibility).
     */
    public function getQuantityAvailableAttribute(): int
    {
        return $this->available;
    }

    /**
     * Get the available quantity (explicit method for Filament compatibility).
     */
    public function getAvailableQuantity(): int
    {
        return $this->available;
    }

    /**
     * Check if inventory is low (below reorder point).
     */
    public function isLowStock(?int $threshold = null): bool
    {
        $threshold ??= $this->reorder_point ?? config('inventory.default_reorder_point', 10);

        return $this->available <= $threshold;
    }

    /**
     * Check if safety stock is breached.
     */
    public function isSafetyStockBreached(): bool
    {
        if ($this->safety_stock === null) {
            return false;
        }

        return $this->available <= $this->safety_stock;
    }

    /**
     * Check if stock exceeds maximum.
     */
    public function isOverStocked(): bool
    {
        if ($this->max_stock === null) {
            return false;
        }

        return $this->quantity_on_hand > $this->max_stock;
    }

    /**
     * Get the current alert status as enum.
     */
    public function getAlertStatusEnum(): AlertStatus
    {
        if ($this->alert_status === null) {
            return AlertStatus::None;
        }

        return AlertStatus::from($this->alert_status);
    }

    /**
     * Check if available quantity is sufficient.
     */
    public function hasAvailable(int $quantity): bool
    {
        return $this->available >= $quantity;
    }

    /**
     * Get the effective allocation strategy (own or global).
     */
    public function getEffectiveAllocationStrategy(): AllocationStrategy
    {
        if ($this->allocation_strategy !== null) {
            return AllocationStrategy::from($this->allocation_strategy);
        }

        $global = config('inventory.allocation_strategy', 'priority');

        return AllocationStrategy::from($global);
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
     * Scope to filter low stock items.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLowStock(Builder $query, ?int $threshold = null): Builder
    {
        $threshold ??= config('inventory.default_reorder_point', 10);

        return $query->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold]);
    }

    /**
     * Scope to filter items with available stock.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithAvailable(Builder $query, int $minQuantity = 1): Builder
    {
        return $query->whereRaw('(quantity_on_hand - quantity_reserved) >= ?', [$minQuantity]);
    }

    /**
     * Scope to filter by alert status.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithAlertStatus(Builder $query, AlertStatus $status): Builder
    {
        return $query->where('alert_status', $status->value);
    }

    /**
     * Scope to filter items needing reorder.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNeedsReorder(Builder $query): Builder
    {
        return $query->whereIn('alert_status', [
            AlertStatus::LowStock->value,
            AlertStatus::SafetyBreached->value,
            AlertStatus::OutOfStock->value,
        ]);
    }

    /**
     * Scope to filter safety stock breached items.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSafetyStockBreached(Builder $query): Builder
    {
        return $query->whereNotNull('safety_stock')
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= safety_stock');
    }

    /**
     * Scope to filter over-stocked items.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverStocked(Builder $query): Builder
    {
        return $query->whereNotNull('max_stock')
            ->whereRaw('quantity_on_hand > max_stock');
    }

    /**
     * Handle model lifecycle events.
     */
    protected static function booted(): void
    {
        static::saving(function (InventoryLevel $level): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $location = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($level->location_id)
                ->first();

            if ($location === null) {
                throw new AuthorizationException('Invalid location for current owner context.');
            }

            if (($level->owner_type !== null || $level->owner_id !== null)
                && ($level->owner_type !== $location->owner_type || $level->owner_id !== $location->owner_id)) {
                throw new AuthorizationException('Owner mismatch between inventory level and location.');
            }

            $level->owner_type = $location->owner_type;
            $level->owner_id = $location->owner_id;
        });

        self::deleting(function (InventoryLevel $level): void {
            $level->allocations()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InventoryLevelFactory
    {
        return InventoryLevelFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_on_hand_decimal' => 'decimal:4',
            'quantity_reserved_decimal' => 'decimal:4',
            'reorder_point' => 'integer',
            'safety_stock' => 'integer',
            'max_stock' => 'integer',
            'lead_time_days' => 'integer',
            'unit_conversion_factor' => 'decimal:4',
            'last_alert_at' => 'datetime',
            'last_stock_check_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
