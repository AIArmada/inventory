<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Enums\CostingMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $location_id
 * @property CostingMethod $costing_method
 * @property \Illuminate\Support\Carbon $snapshot_date
 * @property int $total_quantity
 * @property int $total_value_minor
 * @property int $average_unit_cost_minor
 * @property string $currency
 * @property int $sku_count
 * @property int|null $variance_from_previous_minor
 * @property array<string, mixed>|null $breakdown
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read InventoryLocation|null $location
 */
class InventoryValuationSnapshot extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'location_id',
        'costing_method',
        'snapshot_date',
        'total_quantity',
        'total_value_minor',
        'average_unit_cost_minor',
        'currency',
        'sku_count',
        'variance_from_previous_minor',
        'breakdown',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('inventory.table_names.valuation_snapshots', 'inventory_valuation_snapshots');
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForLocation(\Illuminate\Database\Eloquent\Builder $query, string $locationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAllLocations(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('location_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUsingMethod(\Illuminate\Database\Eloquent\Builder $query, CostingMethod $method): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('costing_method', $method->value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOnDate(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Support\Carbon $date): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereDate('snapshot_date', $date);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeBetweenDates(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereBetween('snapshot_date', [$from, $to]);
    }

    /**
     * Order by snapshot_date descending (most recent first).
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeLatestBySnapshotDate(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('snapshot_date', 'desc');
    }

    public function hasVariance(): bool
    {
        return $this->variance_from_previous_minor !== null && $this->variance_from_previous_minor !== 0;
    }

    public function variancePercentage(): ?float
    {
        if ($this->variance_from_previous_minor === null) {
            return null;
        }

        $previousValue = $this->total_value_minor - $this->variance_from_previous_minor;
        if ($previousValue === 0) {
            return null;
        }

        return ($this->variance_from_previous_minor / $previousValue) * 100;
    }

    public function isPositiveVariance(): bool
    {
        return $this->variance_from_previous_minor !== null && $this->variance_from_previous_minor > 0;
    }

    public function isNegativeVariance(): bool
    {
        return $this->variance_from_previous_minor !== null && $this->variance_from_previous_minor < 0;
    }

    /**
     * @return array{type: string, units: int, value: int}|null
     */
    public function getBreakdownByType(string $type): ?array
    {
        if ($this->breakdown === null) {
            return null;
        }

        return $this->breakdown[$type] ?? null;
    }

    protected function casts(): array
    {
        return [
            'costing_method' => CostingMethod::class,
            'snapshot_date' => 'date',
            'total_quantity' => 'integer',
            'total_value_minor' => 'integer',
            'average_unit_cost_minor' => 'integer',
            'sku_count' => 'integer',
            'variance_from_previous_minor' => 'integer',
            'breakdown' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
