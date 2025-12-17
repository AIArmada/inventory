<?php

declare(strict_types=1);

use AIArmada\Cart\Events\Store\EloquentCartEventRepository;
use AIArmada\Cart\Models\CartEvent;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentCartEventRepository', function (): void {
    beforeEach(function (): void {
        $this->repository = new EloquentCartEventRepository;
    });

    it('can be instantiated', function (): void {
        expect($this->repository)->toBeInstanceOf(EloquentCartEventRepository::class);
    });

    it('records a single event', function (): void {
        $event = Mockery::mock(CartEventInterface::class);
        $event->shouldReceive('getEventType')->andReturn('item_added');
        $event->shouldReceive('getEventId')->andReturn('event-123');
        $event->shouldReceive('toEventPayload')->andReturn(['item_id' => 'prod-1']);
        $event->shouldReceive('getEventMetadata')->andReturn(['source' => 'test']);
        $event->shouldReceive('getAggregateVersion')->andReturn(1);
        $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

        $eventId = $this->repository->record($event, 'cart-001');

        expect($eventId)->toBeString();

        $storedEvent = CartEvent::find($eventId);
        expect($storedEvent)->not->toBeNull()
            ->and($storedEvent->cart_id)->toBe('cart-001')
            ->and($storedEvent->event_type)->toBe('item_added')
            ->and($storedEvent->stream_position)->toBe(1);
    });

    it('increments stream position for subsequent events', function (): void {
        $event1 = Mockery::mock(CartEventInterface::class);
        $event1->shouldReceive('getEventType')->andReturn('item_added');
        $event1->shouldReceive('getEventId')->andReturn('event-1');
        $event1->shouldReceive('toEventPayload')->andReturn([]);
        $event1->shouldReceive('getEventMetadata')->andReturn([]);
        $event1->shouldReceive('getAggregateVersion')->andReturn(1);
        $event1->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

        $event2 = Mockery::mock(CartEventInterface::class);
        $event2->shouldReceive('getEventType')->andReturn('item_updated');
        $event2->shouldReceive('getEventId')->andReturn('event-2');
        $event2->shouldReceive('toEventPayload')->andReturn([]);
        $event2->shouldReceive('getEventMetadata')->andReturn([]);
        $event2->shouldReceive('getAggregateVersion')->andReturn(2);
        $event2->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

        $id1 = $this->repository->record($event1, 'cart-002');
        $id2 = $this->repository->record($event2, 'cart-002');

        $stored1 = CartEvent::find($id1);
        $stored2 = CartEvent::find($id2);

        expect($stored1->stream_position)->toBe(1)
            ->and($stored2->stream_position)->toBe(2);
    });

    it('records batch of events atomically', function (): void {
        $events = [];
        for ($i = 1; $i <= 3; $i++) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn("event_type_{$i}");
            $event->shouldReceive('getEventId')->andReturn("event-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn(['index' => $i]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);
            $events[] = $event;
        }

        $eventIds = $this->repository->recordBatch($events, 'cart-003');

        expect($eventIds)->toHaveCount(3);

        $storedEvents = CartEvent::where('cart_id', 'cart-003')
            ->orderBy('stream_position')
            ->get();

        expect($storedEvents)->toHaveCount(3)
            ->and($storedEvents[0]->stream_position)->toBe(1)
            ->and($storedEvents[1]->stream_position)->toBe(2)
            ->and($storedEvents[2]->stream_position)->toBe(3);
    });

    it('returns empty array for empty batch', function (): void {
        $eventIds = $this->repository->recordBatch([], 'cart-empty');

        expect($eventIds)->toBeEmpty();
    });

    it('skips non-CartEventInterface items in batch', function (): void {
        $validEvent = Mockery::mock(CartEventInterface::class);
        $validEvent->shouldReceive('getEventType')->andReturn('valid_event');
        $validEvent->shouldReceive('getEventId')->andReturn('event-valid');
        $validEvent->shouldReceive('toEventPayload')->andReturn([]);
        $validEvent->shouldReceive('getEventMetadata')->andReturn([]);
        $validEvent->shouldReceive('getAggregateVersion')->andReturn(1);
        $validEvent->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

        $events = ['invalid', $validEvent, new stdClass];

        $eventIds = $this->repository->recordBatch($events, 'cart-mixed');

        expect($eventIds)->toHaveCount(1);
    });

    it('gets events for a cart in stream order', function (): void {
        // Create test events
        for ($i = 1; $i <= 3; $i++) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn("event_{$i}");
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-get-events');
        }

        $events = $this->repository->getEventsForCart('cart-get-events');

        expect($events)->toHaveCount(3)
            ->and($events[0]->stream_position)->toBe(1)
            ->and($events[1]->stream_position)->toBe(2)
            ->and($events[2]->stream_position)->toBe(3);
    });

    it('gets events from a specific position', function (): void {
        for ($i = 1; $i <= 5; $i++) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn("event_{$i}");
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-from-pos');
        }

        $events = $this->repository->getEventsForCart('cart-from-pos', 2);

        expect($events)->toHaveCount(3)
            ->and($events[0]->stream_position)->toBe(3)
            ->and($events[1]->stream_position)->toBe(4)
            ->and($events[2]->stream_position)->toBe(5);
    });

    it('gets events by type', function (): void {
        $types = ['item_added', 'item_removed', 'item_added'];
        foreach ($types as $i => $type) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn($type);
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i + 1);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-by-type');
        }

        $addedEvents = $this->repository->getEventsByType('cart-by-type', 'item_added');
        $removedEvents = $this->repository->getEventsByType('cart-by-type', 'item_removed');

        expect($addedEvents)->toHaveCount(2)
            ->and($removedEvents)->toHaveCount(1);
    });

    it('gets latest stream position', function (): void {
        for ($i = 1; $i <= 4; $i++) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn('event');
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-position');
        }

        $position = $this->repository->getLatestPosition('cart-position');

        expect($position)->toBe(4);
    });

    it('returns zero for cart with no events', function (): void {
        $position = $this->repository->getLatestPosition('cart-nonexistent');

        expect($position)->toBe(0);
    });

    it('gets latest aggregate version', function (): void {
        $versions = [1, 2, 5];
        foreach ($versions as $i => $version) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn('event');
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($version);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-version');
        }

        $version = $this->repository->getLatestVersion('cart-version');

        expect($version)->toBe(5);
    });

    it('returns zero version for cart with no events', function (): void {
        $version = $this->repository->getLatestVersion('cart-no-version');

        expect($version)->toBe(0);
    });

    it('gets event count for a cart', function (): void {
        for ($i = 1; $i <= 7; $i++) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn('event');
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-count');
        }

        $count = $this->repository->getEventCount('cart-count');

        expect($count)->toBe(7);
    });

    it('returns zero count for cart with no events', function (): void {
        $count = $this->repository->getEventCount('cart-no-events');

        expect($count)->toBe(0);
    });

    it('deletes all events for a cart', function (): void {
        for ($i = 1; $i <= 5; $i++) {
            $event = Mockery::mock(CartEventInterface::class);
            $event->shouldReceive('getEventType')->andReturn('event');
            $event->shouldReceive('getEventId')->andReturn("evt-{$i}");
            $event->shouldReceive('toEventPayload')->andReturn([]);
            $event->shouldReceive('getEventMetadata')->andReturn([]);
            $event->shouldReceive('getAggregateVersion')->andReturn($i);
            $event->shouldReceive('getOccurredAt')->andReturn(new DateTimeImmutable);

            $this->repository->record($event, 'cart-delete');
        }

        $deletedCount = $this->repository->deleteEventsForCart('cart-delete');

        expect($deletedCount)->toBe(5)
            ->and($this->repository->getEventCount('cart-delete'))->toBe(0);
    });

    it('returns zero when deleting events for empty cart', function (): void {
        $deletedCount = $this->repository->deleteEventsForCart('cart-empty-delete');

        expect($deletedCount)->toBe(0);
    });
});
