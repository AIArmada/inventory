<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\States\BackorderStatus;
use AIArmada\Inventory\States\Cancelled;
use AIArmada\Inventory\States\Expired;
use AIArmada\Inventory\States\Fulfilled;
use AIArmada\Inventory\States\PartiallyFulfilled;
use AIArmada\Inventory\States\Pending;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $location_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string|null $order_id
 * @property string|null $customer_id
 * @property int $quantity_requested
 * @property int $quantity_fulfilled
 * @property int $quantity_cancelled
 * @property BackorderStatus $status
 * @property BackorderPriority $priority
 * @property \Carbon\CarbonInterface|null $requested_at
 * @property \Carbon\CarbonInterface|null $promised_at
 * @property \Carbon\CarbonInterface|null $fulfilled_at
 * @property \Carbon\CarbonInterface|null $cancelled_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read Model $inventoryable
 * @property-read InventoryLocation|null $location
 */
class InventoryBackorder extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'owner_type',
        'owner_id',
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

    protected static function booted(): void
    {
        static::creating(function (InventoryBackorder $backorder): void {
            if ($backorder->requested_at === null) {
                $backorder->requested_at = now();
            }
        });

        static::saving(function (InventoryBackorder $backorder): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $owner = InventoryOwnerScope::resolveOwner();

            if ($backorder->owner_type === null xor $backorder->owner_id === null) {
                throw new AuthorizationException('Owner fields must be both set or both null.');
            }

            if ($owner === null) {
                if ($backorder->owner_type !== null || $backorder->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned backorders without an owner context.');
                }

                return;
            }

            if ($backorder->location_id !== null) {
                $location = InventoryLocation::query()
                    ->select(['id', 'owner_type', 'owner_id'])
                    ->find($backorder->location_id);

                if (! $location instanceof InventoryLocation) {
                    throw new AuthorizationException('Invalid location for backorder in current owner context.');
                }

                if ($location->owner_type === null || $location->owner_id === null) {
                    $backorder->removeOwner();

                    return;
                }

                $backorder->owner_type = $location->owner_type;
                $backorder->owner_id = $location->owner_id;

                return;
            }

            if (! (bool) config('inventory.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($backorder->owner_type !== null || $backorder->owner_id !== null) {
                return;
            }

            $backorder->assignOwner($owner);
        });
    }

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
            BackorderStatus::normalize(Pending::class),
            BackorderStatus::normalize(PartiallyFulfilled::class),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', BackorderStatus::normalize(Pending::class));
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
            ? Fulfilled::class
            : PartiallyFulfilled::class;

        return $this->update([
            'quantity_fulfilled' => $newFulfilled,
            'status' => $newStatus,
            'fulfilled_at' => $newStatus === Fulfilled::class ? now() : null,
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
            ? Cancelled::class
            : $this->status;

        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['cancellation_reason'] = $reason;
        }

        return $this->update([
            'quantity_cancelled' => $newCancelled,
            'status' => $newStatus,
            'cancelled_at' => $newStatus === Cancelled::class ? now() : null,
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
            'status' => Expired::class,
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
