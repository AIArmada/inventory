<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events\Store;

use AIArmada\Cart\Models\CartEvent;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;

/**
 * Eloquent implementation of cart event repository.
 *
 * Stores cart events using the CartEvent Eloquent model.
 */
final class EloquentCartEventRepository implements CartEventRepositoryInterface
{
    /**
     * Record a cart event to the store.
     *
     * @param  CartEventInterface  $event  The event to record
     * @param  string  $cartId  The cart UUID
     * @return string The stored event's UUID
     */
    public function record(CartEventInterface $event, string $cartId): string
    {
        $streamPosition = $this->getLatestPosition($cartId) + 1;

        $cartEvent = CartEvent::create([
            'cart_id' => $cartId,
            'event_type' => $event->getEventType(),
            'event_id' => $event->getEventId(),
            'payload' => $event->toEventPayload(),
            'metadata' => $event->getEventMetadata(),
            'aggregate_version' => $event->getAggregateVersion(),
            'stream_position' => $streamPosition,
            'occurred_at' => $event->getOccurredAt(),
        ]);

        return $cartEvent->id;
    }

    /**
     * Record multiple events atomically.
     *
     * @param  array<CartEventInterface>  $events  Events to record
     * @param  string  $cartId  The cart UUID
     * @return array<string> Array of stored event UUIDs
     */
    public function recordBatch(array $events, string $cartId): array
    {
        if (empty($events)) {
            return [];
        }

        $streamPosition = $this->getLatestPosition($cartId);
        $eventIds = [];

        // Use transaction for atomic recording
        \Illuminate\Support\Facades\DB::transaction(function () use ($events, $cartId, &$streamPosition, &$eventIds): void {
            foreach ($events as $event) {
                if (! $event instanceof CartEventInterface) {
                    continue;
                }

                $streamPosition++;

                $cartEvent = CartEvent::create([
                    'cart_id' => $cartId,
                    'event_type' => $event->getEventType(),
                    'event_id' => $event->getEventId(),
                    'payload' => $event->toEventPayload(),
                    'metadata' => $event->getEventMetadata(),
                    'aggregate_version' => $event->getAggregateVersion(),
                    'stream_position' => $streamPosition,
                    'occurred_at' => $event->getOccurredAt(),
                ]);

                $eventIds[] = $cartEvent->id;
            }
        });

        return $eventIds;
    }

    /**
     * Get all events for a cart in stream order.
     *
     * @param  string  $cartId  The cart UUID
     * @param  int  $fromPosition  Start from this stream position (exclusive)
     * @return array<CartEvent>
     */
    public function getEventsForCart(string $cartId, int $fromPosition = 0): array
    {
        return CartEvent::query()
            ->where('cart_id', $cartId)
            ->where('stream_position', '>', $fromPosition)
            ->orderBy('stream_position', 'asc')
            ->get()
            ->all();
    }

    /**
     * Get events for a cart filtered by type.
     *
     * @param  string  $cartId  The cart UUID
     * @param  string  $eventType  Event type to filter
     * @return array<CartEvent>
     */
    public function getEventsByType(string $cartId, string $eventType): array
    {
        return CartEvent::query()
            ->where('cart_id', $cartId)
            ->where('event_type', $eventType)
            ->orderBy('stream_position', 'asc')
            ->get()
            ->all();
    }

    /**
     * Get the latest stream position for a cart.
     *
     * @param  string  $cartId  The cart UUID
     * @return int Latest stream position (0 if no events)
     */
    public function getLatestPosition(string $cartId): int
    {
        return (int) CartEvent::query()
            ->where('cart_id', $cartId)
            ->max('stream_position') ?? 0;
    }

    /**
     * Get the latest aggregate version for a cart.
     *
     * @param  string  $cartId  The cart UUID
     * @return int Latest aggregate version (0 if no events)
     */
    public function getLatestVersion(string $cartId): int
    {
        return (int) CartEvent::query()
            ->where('cart_id', $cartId)
            ->max('aggregate_version') ?? 0;
    }

    /**
     * Get event count for a cart.
     *
     * @param  string  $cartId  The cart UUID
     */
    public function getEventCount(string $cartId): int
    {
        return CartEvent::query()
            ->where('cart_id', $cartId)
            ->count();
    }

    /**
     * Delete all events for a cart (for cleanup).
     *
     * Use with caution - this destroys audit history.
     *
     * @param  string  $cartId  The cart UUID
     * @return int Number of events deleted
     */
    public function deleteEventsForCart(string $cartId): int
    {
        return CartEvent::query()
            ->where('cart_id', $cartId)
            ->delete();
    }
}
