<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events\Concerns;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait providing common event data functionality for inventory events.
 *
 * Implements InventoryEventInterface methods for event sourcing and analytics.
 * Use in inventory event classes that need event store integration.
 */
trait HasInventoryEventData
{
    /**
     * Unique event identifier.
     */
    protected string $eventId;

    /**
     * Timestamp when event occurred.
     */
    protected DateTimeImmutable $occurredAt;

    /**
     * Whether this event should be persisted.
     */
    protected bool $persist = true;

    /**
     * Get the event type name.
     */
    abstract public function getEventType(): string;

    abstract protected function resolveQuantity(): int;

    /**
     * Get the unique event identifier.
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * Get when the event occurred.
     */
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Get the inventoryable model class.
     */
    public function getInventoryableType(): string
    {
        return $this->inventoryable->getMorphClass();
    }

    /**
     * Get the inventoryable model ID.
     */
    public function getInventoryableId(): string | int
    {
        return $this->inventoryable->getKey();
    }

    /**
     * Get the quantity affected by this event.
     */
    public function getQuantity(): int
    {
        return $this->resolveQuantity();
    }

    /**
     * Get the location ID if applicable.
     */
    public function getLocationId(): ?string
    {
        return null;
    }

    /**
     * Get the cart ID if this event relates to a cart operation.
     */
    public function getCartId(): ?string
    {
        return $this->cartId;
    }

    /**
     * Determine if this event should be persisted.
     */
    public function shouldPersist(): bool
    {
        return $this->persist;
    }

    /**
     * Set whether this event should be persisted.
     */
    public function withPersistence(bool $persist): static
    {
        $clone = clone $this;
        $clone->persist = $persist;

        return $clone;
    }

    /**
     * Create an event without persistence (for replays, testing).
     */
    public function withoutPersistence(): static
    {
        return $this->withPersistence(false);
    }

    /**
     * Convert event to a storable payload.
     *
     * @return array<string, mixed>
     */
    public function toEventPayload(): array
    {
        return [
            'event_type' => $this->getEventType(),
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format('c'),
            'inventoryable_type' => $this->getInventoryableType(),
            'inventoryable_id' => $this->getInventoryableId(),
            'quantity' => $this->getQuantity(),
            'location_id' => $this->getLocationId(),
            'cart_id' => $this->getCartId(),
        ];
    }

    /**
     * Get event metadata for storage.
     *
     * @return array<string, mixed>
     */
    public function getEventMetadata(): array
    {
        return [
            'source' => 'inventory',
            'version' => '1.0',
            'timestamp' => $this->occurredAt->format('c'),
        ];
    }

    /**
     * Initialize event data. Call in constructor.
     */
    protected function initializeEventData(): void
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = new DateTimeImmutable;
    }
}
