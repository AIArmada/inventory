<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\Concerns\HasCartEventData;
use AIArmada\Cart\Events\MetadataBatchAdded;
use Tests\Support\Cart\InMemoryStorage;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;

describe('HasCartEventData trait', function (): void {
    beforeEach(function (): void {
        // Create a concrete implementation to test the trait
        $this->event = new class implements CartEventInterface
        {
            use HasCartEventData;

            public function __construct()
            {
                $this->initializeEventData();
            }

            public function getEventType(): string
            {
                return 'test.event';
            }

            public function getCartIdentifier(): string
            {
                return 'test-identifier';
            }

            public function getCartInstance(): string
            {
                return 'default';
            }

            public function getCartId(): ?string
            {
                return 'test-cart-id';
            }
        };
    });

    it('generates a unique event ID', function (): void {
        $eventId = $this->event->getEventId();

        expect($eventId)->toBeString()
            ->and(mb_strlen($eventId))->toBeGreaterThan(0);
    });

    it('generates different event IDs for different instances', function (): void {
        $event2 = new class implements CartEventInterface
        {
            use HasCartEventData;

            public function __construct()
            {
                $this->initializeEventData();
            }

            public function getEventType(): string
            {
                return 'test.event';
            }

            public function getCartIdentifier(): string
            {
                return 'test-identifier';
            }

            public function getCartInstance(): string
            {
                return 'default';
            }

            public function getCartId(): ?string
            {
                return 'test-cart-id';
            }
        };

        expect($this->event->getEventId())->not->toBe($event2->getEventId());
    });

    it('returns a DateTimeImmutable for occuredAt', function (): void {
        $occurredAt = $this->event->getOccurredAt();

        expect($occurredAt)->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('returns event metadata array', function (): void {
        $metadata = $this->event->getEventMetadata();

        expect($metadata)->toBeArray()
            ->and($metadata)->toHaveKeys(['event_id', 'occurred_at']);
    });

    it('returns empty array from toEventPayload by default', function (): void {
        $payload = $this->event->toEventPayload();

        expect($payload)->toBeArray()
            ->and($payload)->toBeEmpty();
    });

    it('uses toArray if available in toEventPayload', function (): void {
        $eventWithToArray = new class implements CartEventInterface
        {
            use HasCartEventData;

            public function __construct()
            {
                $this->initializeEventData();
            }

            public function getEventType(): string
            {
                return 'test.event';
            }

            public function getCartIdentifier(): string
            {
                return 'test-identifier';
            }

            public function getCartInstance(): string
            {
                return 'default';
            }

            public function getCartId(): ?string
            {
                return 'test-cart-id';
            }

            public function toArray(): array
            {
                return ['custom' => 'data'];
            }
        };

        $payload = $eventWithToArray->toEventPayload();

        expect($payload)->toBe(['custom' => 'data']);
    });
});

describe('MetadataBatchAdded', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'user-123');
        $this->cart->add('item-1', 'Test Product', 1000, 1);
    });

    it('can be instantiated', function (): void {
        $event = new MetadataBatchAdded(
            ['key1' => 'value1', 'key2' => 'value2'],
            $this->cart
        );

        expect($event)->toBeInstanceOf(CartEventInterface::class);
    });

    it('returns correct event type', function (): void {
        $event = new MetadataBatchAdded(['key' => 'value'], $this->cart);

        expect($event->getEventType())->toBe('cart.metadata.batch_added');
    });

    it('provides access to metadata', function (): void {
        $metadata = ['key1' => 'value1', 'key2' => ['nested' => 'data']];
        $event = new MetadataBatchAdded($metadata, $this->cart);

        expect($event->metadata)->toBe($metadata);
    });

    it('provides access to cart', function (): void {
        $event = new MetadataBatchAdded(['key' => 'value'], $this->cart);

        expect($event->cart)->toBe($this->cart);
    });

    it('returns cart identifier', function (): void {
        $event = new MetadataBatchAdded(['key' => 'value'], $this->cart);

        expect($event->getCartIdentifier())->toBe('user-123');
    });

    it('returns cart instance', function (): void {
        $event = new MetadataBatchAdded(['key' => 'value'], $this->cart);

        expect($event->getCartInstance())->toBe('default');
    });

    it('returns cart ID (null for InMemoryStorage)', function (): void {
        $event = new MetadataBatchAdded(['key' => 'value'], $this->cart);

        $cartId = $event->getCartId();

        // InMemoryStorage doesn't track cart UUIDs
        expect($cartId)->toBeNull();
    });

    it('serializes to array', function (): void {
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $event = new MetadataBatchAdded($metadata, $this->cart);

        $array = $event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['metadata', 'keys', 'cart', 'timestamp'])
            ->and($array['metadata'])->toBe($metadata)
            ->and($array['keys'])->toBe(['key1', 'key2'])
            ->and($array['cart'])->toHaveKeys(['identifier', 'instance', 'items_count', 'total']);
    });

    it('initializes event data on construction', function (): void {
        $event = new MetadataBatchAdded(['key' => 'value'], $this->cart);

        expect($event->getEventId())->toBeString()
            ->and($event->getOccurredAt())->toBeInstanceOf(DateTimeImmutable::class);
    });
});
