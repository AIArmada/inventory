<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * CartEvent model for event sourcing and audit trails.
 *
 * Stores all cart-related events for replay, analytics, and debugging.
 *
 * @property string $id
 * @property string $cart_id
 * @property string $event_type
 * @property string $event_id
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $metadata
 * @property int $aggregate_version
 * @property int $stream_position
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CartEvent extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'event_type',
        'event_id',
        'payload',
        'metadata',
        'aggregate_version',
        'stream_position',
        'occurred_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('cart.database.events_table', 'cart_events');
    }

    /**
     * Get the event payload data.
     *
     * @return array<string, mixed>
     */
    public function getPayloadData(): array
    {
        return $this->payload ?? [];
    }

    /**
     * Get the event metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadataData(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Check if this event is of a specific type.
     */
    public function isType(string $type): bool
    {
        return $this->event_type === $type;
    }

    /**
     * Get a specific payload value.
     */
    public function getPayloadValue(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Get a specific metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // No cascade deletes needed - cart_events are immutable audit logs
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'aggregate_version' => 'integer',
            'stream_position' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope to filter by cart ID.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forCart(Builder $query, string $cartId): void
    {
        $query->where('cart_id', $cartId);
    }

    /**
     * Scope to filter by event type.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function ofType(Builder $query, string $eventType): void
    {
        $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter events after a specific stream position.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function afterPosition(Builder $query, int $position): void
    {
        $query->where('stream_position', '>', $position);
    }

    /**
     * Scope to filter events within a time range.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function occurredBetween(Builder $query, DateTimeInterface $start, DateTimeInterface $end): void
    {
        $query->whereBetween('occurred_at', [$start, $end]);
    }

    /**
     * Scope to order by stream position for replay.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function orderedForReplay(Builder $query): void
    {
        $query->orderBy('stream_position', 'asc');
    }
}
