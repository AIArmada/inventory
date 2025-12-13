<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Database\Factories\InventoryMovementFactory;
use AIArmada\Inventory\Enums\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $from_location_id
 * @property string|null $to_location_id
 * @property int $quantity
 * @property string $type
 * @property string|null $reason
 * @property string|null $reference
 * @property string|null $user_id
 * @property string|null $note
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $count
 * @property-read int $movement_count
 * @property-read int $total_quantity
 * @property-read int $total_quantity_moved
 * @property-read float $avg_quantity
 * @property-read Carbon|null $last_occurred_at
 * @property-read InventoryLocation|null $fromLocation
 * @property-read InventoryLocation|null $toLocation
 * @property-read Model $inventoryable
 * @property-read User|null $user
 */
final class InventoryMovement extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventoryMovementFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'from_location_id',
        'to_location_id',
        'quantity',
        'type',
        'reason',
        'reference',
        'user_id',
        'note',
        'occurred_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.movements', 'inventory_movements');
    }

    /**
     * Get the inventoryable model (Product, Variant, etc.)
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the source location (for transfers and shipments).
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    /**
     * Get the destination location (for transfers and receipts).
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    /**
     * Get the user who performed the movement.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<User> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel);
    }

    /**
     * Get the movement type as enum.
     */
    public function getMovementType(): MovementType
    {
        return MovementType::from($this->type);
    }

    /**
     * Check if this is a receipt.
     */
    public function isReceipt(): bool
    {
        return $this->type === MovementType::Receipt->value;
    }

    /**
     * Check if this is a shipment.
     */
    public function isShipment(): bool
    {
        return $this->type === MovementType::Shipment->value;
    }

    /**
     * Check if this is a transfer.
     */
    public function isTransfer(): bool
    {
        return $this->type === MovementType::Transfer->value;
    }

    /**
     * Check if this is an adjustment.
     */
    public function isAdjustment(): bool
    {
        return $this->type === MovementType::Adjustment->value;
    }

    /**
     * Scope to filter by movement type.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, MovementType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope to filter by reference.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForReference(Builder $query, string $reference): Builder
    {
        return $query->where('reference', $reference);
    }

    /**
     * Scope to filter by location (from or to).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAtLocation(Builder $query, string $locationId): Builder
    {
        return $query->where(function ($q) use ($locationId): void {
            $q->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId);
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InventoryMovementFactory
    {
        return InventoryMovementFactory::new();
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
            'occurred_at' => 'datetime',
        ];
    }
}
