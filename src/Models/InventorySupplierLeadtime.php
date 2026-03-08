<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string|null $supplier_id
 * @property string|null $supplier_name
 * @property int $lead_time_days
 * @property int $lead_time_variance_days
 * @property int $minimum_order_quantity
 * @property int $order_multiple
 * @property int|null $unit_cost_minor
 * @property string $currency
 * @property bool $is_primary
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_order_at
 * @property \Illuminate\Support\Carbon|null $last_received_at
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $inventoryable
 */
class InventorySupplierLeadtime extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'owner_type',
        'owner_id',
        'supplier_id',
        'supplier_name',
        'lead_time_days',
        'lead_time_variance_days',
        'minimum_order_quantity',
        'order_multiple',
        'unit_cost_minor',
        'currency',
        'is_primary',
        'is_active',
        'last_order_at',
        'last_received_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::saving(function (InventorySupplierLeadtime $leadtime): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $owner = InventoryOwnerScope::resolveOwner();

            if ($leadtime->owner_type === null xor $leadtime->owner_id === null) {
                throw new AuthorizationException('Owner fields must be both set or both null.');
            }

            if ($owner === null) {
                if ($leadtime->owner_type !== null || $leadtime->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned supplier leadtimes without an owner context.');
                }

                return;
            }

            $inventoryable = $leadtime->inventoryable;
            if ($inventoryable instanceof Model) {
                /** @var string|null $inventoryableOwnerType */
                $inventoryableOwnerType = $inventoryable->getAttribute('owner_type');
                /** @var int|string|null $inventoryableOwnerId */
                $inventoryableOwnerId = $inventoryable->getAttribute('owner_id');

                if ($inventoryableOwnerType !== null && $inventoryableOwnerId !== null) {
                    $leadtime->owner_type = $inventoryableOwnerType;
                    $leadtime->owner_id = $inventoryableOwnerId;

                    return;
                }
            }

            if (! (bool) config('inventory.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($leadtime->owner_type !== null || $leadtime->owner_id !== null) {
                return;
            }

            $leadtime->assignOwner($owner);
        });
    }

    public function getTable(): string
    {
        return config('inventory.table_names.supplier_leadtimes', 'inventory_supplier_leadtimes');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForModel(\Illuminate\Database\Eloquent\Builder $query, Model $model): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePrimary(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrderedByLeadTime(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('lead_time_days', 'asc');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrderedByCost(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('unit_cost_minor', 'asc');
    }

    /**
     * Get maximum lead time including variance.
     */
    public function maxLeadTimeDays(): int
    {
        return $this->lead_time_days + $this->lead_time_variance_days;
    }

    /**
     * Get minimum lead time (optimistic).
     */
    public function minLeadTimeDays(): int
    {
        return max(1, $this->lead_time_days - $this->lead_time_variance_days);
    }

    /**
     * Round up quantity to order multiple.
     */
    public function roundToOrderMultiple(int $quantity): int
    {
        if ($this->order_multiple <= 1) {
            return max($quantity, $this->minimum_order_quantity);
        }

        $rounded = (int) ceil($quantity / $this->order_multiple) * $this->order_multiple;

        return max($rounded, $this->minimum_order_quantity);
    }

    /**
     * Calculate order cost.
     */
    public function calculateOrderCost(int $quantity): ?int
    {
        if ($this->unit_cost_minor === null) {
            return null;
        }

        return $this->roundToOrderMultiple($quantity) * $this->unit_cost_minor;
    }

    /**
     * Mark as primary supplier.
     */
    public function markAsPrimary(): bool
    {
        self::query()
            ->where('inventoryable_type', $this->inventoryable_type)
            ->where('inventoryable_id', $this->inventoryable_id)
            ->where('id', '!=', $this->id)
            ->when(
                $this->owner_type === null || $this->owner_id === null,
                fn (Builder $query): Builder => $query->whereNull('owner_type')->whereNull('owner_id'),
                fn (Builder $query): Builder => $query->where('owner_type', $this->owner_type)->where('owner_id', $this->owner_id),
            )
            ->update(['is_primary' => false]);

        return $this->update(['is_primary' => true]);
    }

    /**
     * Record an order placement.
     */
    public function recordOrder(): bool
    {
        return $this->update(['last_order_at' => now()]);
    }

    /**
     * Record order receipt.
     */
    public function recordReceipt(): bool
    {
        return $this->update(['last_received_at' => now()]);
    }

    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
            'lead_time_variance_days' => 'integer',
            'minimum_order_quantity' => 'integer',
            'order_multiple' => 'integer',
            'unit_cost_minor' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'last_order_at' => 'datetime',
            'last_received_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
