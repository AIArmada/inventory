<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events\Concerns;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Trait providing common functionality for cart events implementing CartEventInterface.
 *
 * Use this trait in cart event classes to satisfy the CartEventInterface contract
 * with sensible defaults and common implementation patterns.
 */
trait HasCartEventData
{
    private string $eventId;

    private DateTimeImmutable $occurredAt;

    private int $aggregateVersion = 1;

    private bool $shouldPersist = true;

    /**
     * Get a unique identifier for this event occurrence.
     */
    public function getEventId(): string
    {
        return $this->eventId ?? (string) Str::uuid();
    }

    /**
     * Get the timestamp when the event occurred.
     */
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt ?? new DateTimeImmutable();
    }

    /**
     * Get the aggregate version at the time of this event.
     */
    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    /**
     * Set the aggregate version for this event.
     */
    public function setAggregateVersion(int $version): static
    {
        $this->aggregateVersion = $version;

        return $this;
    }

    /**
     * Check if this event should be persisted to the event store.
     */
    public function shouldPersist(): bool
    {
        return $this->shouldPersist;
    }

    /**
     * Mark this event as non-persistable.
     */
    public function doNotPersist(): static
    {
        $this->shouldPersist = false;

        return $this;
    }

    /**
     * Get event metadata for logging and tracing.
     *
     * @return array<string, mixed>
     */
    public function getEventMetadata(): array
    {
        return [
            'event_id' => $this->getEventId(),
            'occurred_at' => $this->getOccurredAt()->format('c'),
            'aggregate_version' => $this->getAggregateVersion(),
            'user_agent' => request()?->userAgent(),
            'ip_address' => request()?->ip(),
            'correlation_id' => request()?->header('X-Correlation-ID'),
        ];
    }

    /**
     * Get the event payload as an array.
     *
     * Override this in your event class to provide specific payload data.
     *
     * @return array<string, mixed>
     */
    public function toEventPayload(): array
    {
        // Default implementation uses toArray if available
        if (method_exists($this, 'toArray')) {
            return $this->toArray();
        }

        return [];
    }

    /**
     * Initialize event data. Call this in the event constructor.
     */
    protected function initializeEventData(): void
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = new DateTimeImmutable();
    }
}
