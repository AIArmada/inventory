<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\Concerns\HasInventoryEventData;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Event Test Product']);
});

class TestEventWithTrait
{
    use HasInventoryEventData;

    public function __construct(
        public Model $inventoryable,
        public int $quantity = 10,
        public ?string $locationId = null,
        public ?string $cartId = null
    ) {
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'test.event';
    }
}

class TestEventWithAllocations
{
    use HasInventoryEventData;

    public Collection $allocations;

    public function __construct()
    {
        $this->allocations = new Collection();
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'allocation.event';
    }

    public function getTotalQuantity(): int
    {
        return 25;
    }
}

class TestEventWithUnderscoreProperties
{
    use HasInventoryEventData;

    public string $location_id = 'loc-underscore';

    public string $cart_id = 'cart-underscore';

    public function __construct()
    {
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'underscore.event';
    }
}

class TestEventMinimal
{
    use HasInventoryEventData;

    public function __construct()
    {
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'minimal.event';
    }
}

describe('HasInventoryEventData', function (): void {
    describe('initializeEventData', function (): void {
        it('generates unique event id', function (): void {
            $event = new TestEventWithTrait($this->item);

            expect($event->getEventId())->not->toBeEmpty();
            expect(strlen($event->getEventId()))->toBe(36); // UUID length
        });

        it('sets occurred at to current time', function (): void {
            $event = new TestEventWithTrait($this->item);

            expect($event->getOccurredAt())->toBeInstanceOf(DateTimeImmutable::class);
            expect($event->getOccurredAt()->getTimestamp())->toBeGreaterThan(time() - 5);
        });
    });

    describe('getInventoryableType', function (): void {
        it('returns morph class from inventoryable', function (): void {
            $event = new TestEventWithTrait($this->item);

            expect($event->getInventoryableType())->toBe($this->item->getMorphClass());
        });

        it('returns empty string when no inventoryable', function (): void {
            $event = new TestEventMinimal();

            expect($event->getInventoryableType())->toBe('');
        });
    });

    describe('getInventoryableId', function (): void {
        it('returns key from inventoryable', function (): void {
            $event = new TestEventWithTrait($this->item);

            expect($event->getInventoryableId())->toBe($this->item->getKey());
        });

        it('returns empty string when no inventoryable', function (): void {
            $event = new TestEventMinimal();

            expect($event->getInventoryableId())->toBe('');
        });
    });

    describe('getQuantity', function (): void {
        it('returns quantity property', function (): void {
            $event = new TestEventWithTrait($this->item, 50);

            expect($event->getQuantity())->toBe(50);
        });

        it('calls getTotalQuantity for allocations events', function (): void {
            $event = new TestEventWithAllocations();

            expect($event->getQuantity())->toBe(25);
        });

        it('returns 0 when no quantity available', function (): void {
            $event = new TestEventMinimal();

            expect($event->getQuantity())->toBe(0);
        });
    });

    describe('getLocationId', function (): void {
        it('returns locationId property', function (): void {
            $event = new TestEventWithTrait($this->item, 10, 'loc-123');

            expect($event->getLocationId())->toBe('loc-123');
        });

        it('returns location_id property', function (): void {
            $event = new TestEventWithUnderscoreProperties();

            expect($event->getLocationId())->toBe('loc-underscore');
        });

        it('returns null when no location', function (): void {
            $event = new TestEventMinimal();

            expect($event->getLocationId())->toBeNull();
        });
    });

    describe('getCartId', function (): void {
        it('returns cartId property', function (): void {
            $event = new TestEventWithTrait($this->item, 10, null, 'cart-123');

            expect($event->getCartId())->toBe('cart-123');
        });

        it('returns cart_id property', function (): void {
            $event = new TestEventWithUnderscoreProperties();

            expect($event->getCartId())->toBe('cart-underscore');
        });

        it('returns null when no cart', function (): void {
            $event = new TestEventMinimal();

            expect($event->getCartId())->toBeNull();
        });
    });

    describe('persistence', function (): void {
        it('defaults to persist true', function (): void {
            $event = new TestEventWithTrait($this->item);

            expect($event->shouldPersist())->toBeTrue();
        });

        it('can set persistence via withPersistence', function (): void {
            $event = new TestEventWithTrait($this->item);
            $modified = $event->withPersistence(false);

            expect($modified)->not->toBe($event);
            expect($modified->shouldPersist())->toBeFalse();
            expect($event->shouldPersist())->toBeTrue();
        });

        it('can disable persistence via withoutPersistence', function (): void {
            $event = new TestEventWithTrait($this->item);
            $modified = $event->withoutPersistence();

            expect($modified->shouldPersist())->toBeFalse();
        });
    });

    describe('toEventPayload', function (): void {
        it('returns complete event payload', function (): void {
            $event = new TestEventWithTrait($this->item, 20, 'loc-456', 'cart-789');
            $payload = $event->toEventPayload();

            expect($payload)->toHaveKey('event_type');
            expect($payload)->toHaveKey('event_id');
            expect($payload)->toHaveKey('occurred_at');
            expect($payload)->toHaveKey('inventoryable_type');
            expect($payload)->toHaveKey('inventoryable_id');
            expect($payload)->toHaveKey('quantity');
            expect($payload)->toHaveKey('location_id');
            expect($payload)->toHaveKey('cart_id');

            expect($payload['event_type'])->toBe('test.event');
            expect($payload['quantity'])->toBe(20);
            expect($payload['location_id'])->toBe('loc-456');
            expect($payload['cart_id'])->toBe('cart-789');
        });
    });

    describe('getEventMetadata', function (): void {
        it('returns metadata array', function (): void {
            $event = new TestEventWithTrait($this->item);
            $metadata = $event->getEventMetadata();

            expect($metadata)->toHaveKey('source');
            expect($metadata)->toHaveKey('version');
            expect($metadata)->toHaveKey('timestamp');

            expect($metadata['source'])->toBe('inventory');
            expect($metadata['version'])->toBe('1.0');
        });
    });
});
