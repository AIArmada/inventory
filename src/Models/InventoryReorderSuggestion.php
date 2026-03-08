<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Enums\ReorderUrgency;
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
 * @property string|null $supplier_leadtime_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property ReorderSuggestionStatus $status
 * @property int $current_stock
 * @property int $reorder_point
 * @property int $suggested_quantity
 * @property int|null $economic_order_quantity
 * @property int|null $average_daily_demand
 * @property int|null $lead_time_days
 * @property \Illuminate\Support\Carbon|null $expected_stockout_date
 * @property ReorderUrgency $urgency
 * @property string $trigger_reason
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $order_id
 * @property \Illuminate\Support\Carbon|null $ordered_at
 * @property array<string, mixed>|null $calculation_details
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $inventoryable
 * @property-read InventoryLocation|null $location
 * @property-read InventorySupplierLeadtime|null $supplierLeadtime
 */
class InventoryReorderSuggestion extends Model
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
        'supplier_leadtime_id',
        'owner_type',
        'owner_id',
        'status',
        'current_stock',
        'reorder_point',
        'suggested_quantity',
        'economic_order_quantity',
        'average_daily_demand',
        'lead_time_days',
        'expected_stockout_date',
        'urgency',
        'trigger_reason',
        'approved_by',
        'approved_at',
        'order_id',
        'ordered_at',
        'calculation_details',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::saving(function (InventoryReorderSuggestion $suggestion): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $owner = InventoryOwnerScope::resolveOwner();

            if ($suggestion->owner_type === null xor $suggestion->owner_id === null) {
                throw new AuthorizationException('Owner fields must be both set or both null.');
            }

            if ($owner === null) {
                if ($suggestion->owner_type !== null || $suggestion->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned reorder suggestions without an owner context.');
                }

                return;
            }

            if ($suggestion->location_id !== null) {
                $location = InventoryLocation::query()
                    ->select(['id', 'owner_type', 'owner_id'])
                    ->find($suggestion->location_id);

                if (! $location instanceof InventoryLocation) {
                    throw new AuthorizationException('Invalid location for reorder suggestion in current owner context.');
                }

                if ($location->owner_type === null || $location->owner_id === null) {
                    $suggestion->removeOwner();
                } else {
                    $suggestion->owner_type = $location->owner_type;
                    $suggestion->owner_id = $location->owner_id;
                }
            }

            if ($suggestion->supplier_leadtime_id !== null) {
                $supplierLeadtime = InventorySupplierLeadtime::query()
                    ->select(['id', 'owner_type', 'owner_id'])
                    ->find($suggestion->supplier_leadtime_id);

                if (! $supplierLeadtime instanceof InventorySupplierLeadtime) {
                    throw new AuthorizationException('Invalid supplier leadtime for reorder suggestion in current owner context.');
                }

                if ($suggestion->owner_type !== $supplierLeadtime->owner_type || $suggestion->owner_id !== $supplierLeadtime->owner_id) {
                    throw new AuthorizationException('Reorder suggestion supplier leadtime must belong to the same owner context.');
                }
            }

            if ($suggestion->owner_type !== null || $suggestion->owner_id !== null) {
                return;
            }

            if (! (bool) config('inventory.owner.auto_assign_on_create', true)) {
                return;
            }

            $suggestion->assignOwner($owner);
        });
    }

    public function getTable(): string
    {
        return config('inventory.table_names.reorder_suggestions', 'inventory_reorder_suggestions');
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
     * @return BelongsTo<InventorySupplierLeadtime, $this>
     */
    public function supplierLeadtime(): BelongsTo
    {
        return $this->belongsTo(InventorySupplierLeadtime::class, 'supplier_leadtime_id');
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
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', ReorderSuggestionStatus::Pending->value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActionable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', [
            ReorderSuggestionStatus::Pending->value,
            ReorderSuggestionStatus::Approved->value,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByUrgency(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderByRaw("CASE urgency WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END");
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCritical(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('urgency', ReorderUrgency::Critical->value);
    }

    public function isActionable(): bool
    {
        return $this->status->isActionable();
    }

    public function daysUntilStockout(): ?int
    {
        if ($this->expected_stockout_date === null) {
            return null;
        }

        return (int) now()->diffInDays($this->expected_stockout_date, false);
    }

    /**
     * Approve the suggestion.
     */
    public function approve(?string $approvedBy = null): bool
    {
        if ($this->status !== ReorderSuggestionStatus::Pending) {
            return false;
        }

        return $this->update([
            'status' => ReorderSuggestionStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject the suggestion.
     */
    public function reject(?string $reason = null): bool
    {
        if (! $this->isActionable()) {
            return false;
        }

        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['rejection_reason'] = $reason;
        }

        return $this->update([
            'status' => ReorderSuggestionStatus::Rejected,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Mark as ordered.
     */
    public function markOrdered(string $orderId): bool
    {
        if ($this->status !== ReorderSuggestionStatus::Approved) {
            return false;
        }

        return $this->update([
            'status' => ReorderSuggestionStatus::Ordered,
            'order_id' => $orderId,
            'ordered_at' => now(),
        ]);
    }

    /**
     * Mark as received.
     */
    public function markReceived(): bool
    {
        if ($this->status !== ReorderSuggestionStatus::Ordered) {
            return false;
        }

        return $this->update([
            'status' => ReorderSuggestionStatus::Received,
        ]);
    }

    /**
     * Expire the suggestion.
     */
    public function expire(): bool
    {
        if (! $this->isActionable()) {
            return false;
        }

        return $this->update([
            'status' => ReorderSuggestionStatus::Expired,
        ]);
    }

    protected function casts(): array
    {
        return [
            'status' => ReorderSuggestionStatus::class,
            'urgency' => ReorderUrgency::class,
            'current_stock' => 'integer',
            'reorder_point' => 'integer',
            'suggested_quantity' => 'integer',
            'economic_order_quantity' => 'integer',
            'average_daily_demand' => 'integer',
            'lead_time_days' => 'integer',
            'expected_stockout_date' => 'date',
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
            'calculation_details' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
