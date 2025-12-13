<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Enums\CostingMethod;
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
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'batch_id',
        'quantity',
        'remaining_quantity',
        'unit_cost_minor',
        'total_cost_minor',
        'currency',
        'reference',
        'costing_method',
        'layer_date',
    ];

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
