<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Enums\BackorderStatus;
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
 * @property string|null $order_id
 * @property string|null $customer_id
 * @property int $quantity_requested
 * @property int $quantity_fulfilled
 * @property int $quantity_cancelled
 * @property BackorderStatus $status
 * @property BackorderPriority $priority
 * @property \Illuminate\Support\Carbon $requested_at
 * @property \Illuminate\Support\Carbon|null $promised_at
 * @property \Illuminate\Support\Carbon|null $fulfilled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $inventoryable
 * @property-read InventoryLocation|null $location
 */
class InventoryBackorder extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'order_id',
        'customer_id',
        'quantity_requested',
        'quantity_fulfilled',
        'quantity_cancelled',
        'status',
        'priority',
        'requested_at',
        'promised_at',
        'fulfilled_at',
        'cancelled_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('inventory.table_names.backorders', 'inventory_backorders');
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
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOpen(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', [
            BackorderStatus::Pending->value,
            BackorderStatus::PartiallyFulfilled->value,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', BackorderStatus::Pending->value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByPriority(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END");
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
    public function scopeOverdue(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->open()
            ->whereNotNull('promised_at')
            ->where('promised_at', '<', now());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDueWithin(\Illuminate\Database\Eloquent\Builder $query, int $days): \Illuminate\Database\Eloquent\Builder
    {
        return $query->open()
            ->whereNotNull('promised_at')
            ->where('promised_at', '<=', now()->addDays($days));
    }

    public function quantityRemaining(): int
    {
        return $this->quantity_requested - $this->quantity_fulfilled - $this->quantity_cancelled;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->promised_at !== null
            && $this->promised_at < now();
    }

    public function canFulfill(): bool
    {
        return $this->status->canFulfill() && $this->quantityRemaining() > 0;
    }

    public function canCancel(): bool
    {
        return $this->status->canCancel();
    }

    /**
     * Fulfill a portion of the backorder.
     */
    public function fulfill(int $quantity): bool
    {
        if (! $this->canFulfill()) {
            return false;
        }

        $quantity = min($quantity, $this->quantityRemaining());

        $newFulfilled = $this->quantity_fulfilled + $quantity;
        $newStatus = $newFulfilled >= $this->quantity_requested - $this->quantity_cancelled
            ? BackorderStatus::Fulfilled
            : BackorderStatus::PartiallyFulfilled;

        return $this->update([
            'quantity_fulfilled' => $newFulfilled,
            'status' => $newStatus,
            'fulfilled_at' => $newStatus === BackorderStatus::Fulfilled ? now() : null,
        ]);
    }

    /**
     * Cancel a portion or all of the backorder.
     */
    public function cancel(?int $quantity = null, ?string $reason = null): bool
    {
        if (! $this->canCancel()) {
            return false;
        }

        $quantity = $quantity ?? $this->quantityRemaining();
        $quantity = min($quantity, $this->quantityRemaining());

        $newCancelled = $this->quantity_cancelled + $quantity;
        $remaining = $this->quantity_requested - $this->quantity_fulfilled - $newCancelled;

        $newStatus = $remaining <= 0
            ? BackorderStatus::Cancelled
            : $this->status;

        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['cancellation_reason'] = $reason;
        }

        return $this->update([
            'quantity_cancelled' => $newCancelled,
            'status' => $newStatus,
            'cancelled_at' => $newStatus === BackorderStatus::Cancelled ? now() : null,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Mark as expired.
     */
    public function expire(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return $this->update([
            'status' => BackorderStatus::Expired,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Escalate priority.
     */
    public function escalate(): bool
    {
        $newPriority = match ($this->priority) {
            BackorderPriority::Low => BackorderPriority::Normal,
            BackorderPriority::Normal => BackorderPriority::High,
            BackorderPriority::High, BackorderPriority::Urgent => BackorderPriority::Urgent,
        };

        return $this->update(['priority' => $newPriority]);
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryBackorder $backorder): void {
            if ($backorder->requested_at === null) {
                $backorder->requested_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_fulfilled' => 'integer',
            'quantity_cancelled' => 'integer',
            'status' => BackorderStatus::class,
            'priority' => BackorderPriority::class,
            'requested_at' => 'datetime',
            'promised_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
