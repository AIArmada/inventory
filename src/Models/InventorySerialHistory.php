<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Enums\SerialEventType;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $serial_id
 * @property string $event_type
 * @property string|null $previous_status
 * @property string|null $new_status
 * @property string|null $from_location_id
 * @property string|null $to_location_id
 * @property string|null $related_to_type
 * @property string|null $related_to_id
 * @property string|null $reference
 * @property string|null $user_id
 * @property string|null $actor_name
 * @property string|null $notes
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InventorySerial $serial
 * @property-read InventoryLocation|null $fromLocation
 * @property-read InventoryLocation|null $toLocation
 * @property-read Model|null $relatedTo
 */
final class InventorySerialHistory extends Model
{
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
        'serial_id',
        'event_type',
        'previous_status',
        'new_status',
        'from_location_id',
        'to_location_id',
        'related_to_type',
        'related_to_id',
        'reference',
        'user_id',
        'actor_name',
        'notes',
        'metadata',
        'occurred_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.database.tables.serial_history', 'inventory_serial_history');
    }

    /**
     * Get the serial.
     *
     * @return BelongsTo<InventorySerial, $this>
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'serial_id');
    }

    /**
     * Get the from location.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    /**
     * Get the to location.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    /**
     * Get the related to model.
     */
    public function relatedTo(): MorphTo
    {
        return $this->morphTo('related_to');
    }

    /**
     * Get the event type as enum.
     */
    public function getEventTypeEnum(): SerialEventType
    {
        return SerialEventType::from($this->event_type);
    }

    /**
     * Scope to filter by event type.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, SerialEventType $type): Builder
    {
        return $query->where('event_type', $type->value);
    }

    /**
     * Scope to filter by date range.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBetweenDates(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }

    protected static function booted(): void
    {
        static::saving(function (InventorySerialHistory $history): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $serial = InventorySerial::query()
                ->whereKey($history->serial_id)
                ->first();

            if ($serial === null) {
                throw new AuthorizationException('Invalid serial for current owner context.');
            }

            if ($history->from_location_id !== null) {
                $from = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($history->from_location_id)
                    ->first();

                if ($from === null) {
                    throw new AuthorizationException('Invalid from_location_id for current owner context.');
                }

                if ($serial->owner_type !== $from->owner_type || $serial->owner_id !== $from->owner_id) {
                    throw new AuthorizationException('Serial owner does not match from location owner.');
                }
            }

            if ($history->to_location_id !== null) {
                $to = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($history->to_location_id)
                    ->first();

                if ($to === null) {
                    throw new AuthorizationException('Invalid to_location_id for current owner context.');
                }

                if ($serial->owner_type !== $to->owner_type || $serial->owner_id !== $to->owner_id) {
                    throw new AuthorizationException('Serial owner does not match to location owner.');
                }
            }

            if (($history->owner_type !== null || $history->owner_id !== null)
                && ($history->owner_type !== $serial->owner_type || $history->owner_id !== $serial->owner_id)) {
                throw new AuthorizationException('Owner mismatch for inventory serial history.');
            }

            $history->owner_type = $serial->owner_type;
            $history->owner_id = $serial->owner_id;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
