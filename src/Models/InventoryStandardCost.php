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
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property int $standard_cost_minor
 * @property string $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $approved_by
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $inventoryable
 */
class InventoryStandardCost extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'standard_cost_minor',
        'currency',
        'effective_from',
        'effective_to',
        'approved_by',
        'notes',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::saving(function (InventoryStandardCost $cost): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $owner = InventoryOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($cost->owner_type !== null || $cost->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned inventory standard costs without an owner context.');
                }

                return;
            }

            if (! (bool) config('inventory.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($cost->owner_type !== null || $cost->owner_id !== null) {
                return;
            }

            $cost->assignOwner($owner);
        });
    }

    public function getTable(): string
    {
        return config('inventory.database.tables.standard_costs', 'inventory_standard_costs');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Builder<static>
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());
    }

    /**
     * @return Builder<static>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            });
    }

    /**
     * @return Builder<static>
     */
    public function scopeEffectiveAt(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $date);
            });
    }

    /**
     * @return Builder<static>
     */
    public function scopeFuture(Builder $query): Builder
    {
        return $query->where('effective_from', '>', now());
    }

    /**
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('effective_to')
            ->where('effective_to', '<=', now());
    }

    public function isCurrent(): bool
    {
        $now = now();

        return $this->effective_from <= $now
            && ($this->effective_to === null || $this->effective_to > $now);
    }

    public function isFuture(): bool
    {
        return $this->effective_from > now();
    }

    public function isExpired(): bool
    {
        return $this->effective_to !== null && $this->effective_to <= now();
    }

    public function expire(): bool
    {
        return $this->update(['effective_to' => now()]);
    }

    protected function casts(): array
    {
        return [
            'standard_cost_minor' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
