<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $location_id
 * @property string|null $batch_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property int $quantity
 * @property int $remaining_quantity
 * @property int $unit_cost_minor
 * @property int $total_cost_minor
 * @property string $currency
 * @property string|null $reference
 * @property CostingMethod $costing_method
 * @property \Illuminate\Support\Carbon $layer_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $units
 * @property-read int $value
 * @property-read Model $inventoryable
 * @property-read InventoryLocation|null $location
 * @property-read InventoryBatch|null $batch
 */
class InventoryCostLayer extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'batch_id',
        'owner_type',
        'owner_id',
        'quantity',
        'remaining_quantity',
        'unit_cost_minor',
        'total_cost_minor',
        'currency',
        'reference',
        'costing_method',
        'layer_date',
    ];

    protected static function booted(): void
    {
        static::saving(function (InventoryCostLayer $layer): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $resolvedOwnerType = null;
            $resolvedOwnerId = null;
            $ownerResolvedFromRelation = false;

            if ($layer->location_id !== null) {
                $location = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($layer->location_id)
                    ->first();

                if ($location === null) {
                    throw new AuthorizationException('Invalid location for current owner context.');
                }

                $resolvedOwnerType = $location->owner_type;
                $resolvedOwnerId = $location->owner_id;
                $ownerResolvedFromRelation = true;
            }

            if ($layer->batch_id !== null) {
                $batch = InventoryBatch::query()
                    ->whereKey($layer->batch_id)
                    ->first();

                if ($batch === null) {
                    throw new AuthorizationException('Invalid batch for current owner context.');
                }

                if ($layer->location_id !== null && $batch->location_id !== $layer->location_id) {
                    throw new AuthorizationException('Batch location does not match cost layer location.');
                }

                if (($resolvedOwnerType !== null || $resolvedOwnerId !== null)
                    && ($resolvedOwnerType !== $batch->owner_type || $resolvedOwnerId !== $batch->owner_id)) {
                    throw new AuthorizationException('Owner mismatch between cost layer location and batch.');
                }

                $resolvedOwnerType = $batch->owner_type;
                $resolvedOwnerId = $batch->owner_id;
                $ownerResolvedFromRelation = true;
            }

            if ($ownerResolvedFromRelation && $resolvedOwnerType === null && $resolvedOwnerId === null) {
                if ($layer->owner_type !== null || $layer->owner_id !== null) {
                    throw new AuthorizationException('Owner mismatch for inventory cost layer.');
                }

                $layer->removeOwner();

                return;
            }

            if (! $ownerResolvedFromRelation && $resolvedOwnerType === null && $resolvedOwnerId === null) {
                if ($layer->owner_type !== null || $layer->owner_id !== null) {
                    return;
                }

                if (! (bool) config('inventory.owner.auto_assign_on_create', true)) {
                    return;
                }

                $owner = InventoryOwnerScope::resolveOwner();

                if ($owner === null) {
                    return;
                }

                $layer->assignOwner($owner);

                return;
            }

            if (($layer->owner_type !== null || $layer->owner_id !== null)
                && ($layer->owner_type !== $resolvedOwnerType || $layer->owner_id !== $resolvedOwnerId)) {
                throw new AuthorizationException('Owner mismatch for inventory cost layer.');
            }

            $layer->owner_type = $resolvedOwnerType;
            $layer->owner_id = $resolvedOwnerId;
        });
    }

    public function getTable(): string
    {
        return config('inventory.table_names.cost_layers', 'inventory_cost_layers');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithRemainingQuantity(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('remaining_quantity', '>', 0);
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
    public function scopeFifoOrder(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('layer_date', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeLifoOrder(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('layer_date', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUsingMethod(\Illuminate\Database\Eloquent\Builder $query, CostingMethod $method): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('costing_method', $method->value);
    }

    public function hasRemainingQuantity(): bool
    {
        return $this->remaining_quantity > 0;
    }

    public function isFullyConsumed(): bool
    {
        return $this->remaining_quantity === 0;
    }

    public function consumedQuantity(): int
    {
        return $this->quantity - $this->remaining_quantity;
    }

    public function remainingValue(): int
    {
        return $this->remaining_quantity * $this->unit_cost_minor;
    }

    public function consumedValue(): int
    {
        return $this->consumedQuantity() * $this->unit_cost_minor;
    }

    /**
     * Consume quantity from this layer.
     *
     * @return int The quantity actually consumed
     */
    public function consume(int $quantity): int
    {
        $consumed = min($quantity, $this->remaining_quantity);

        $this->update([
            'remaining_quantity' => $this->remaining_quantity - $consumed,
        ]);

        return $consumed;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'unit_cost_minor' => 'integer',
            'total_cost_minor' => 'integer',
            'costing_method' => CostingMethod::class,
            'layer_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
