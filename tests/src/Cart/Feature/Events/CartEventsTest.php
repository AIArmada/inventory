<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use Illuminate\Events\Dispatcher;
use Tests\Support\Cart\InMemoryStorage;

/**
 * Comprehensive tests for cart event system
 *
 * Tests all cart events including the condition events to ensure
 * proper event dispatching, data integrity, and event listener compatibility.
 */
describe('Cart Events', function (): void {
    beforeEach(function (): void {
        $this->events = new Dispatcher;
        $this->dispatchedEvents = [];

        // Set up event listeners to capture all dispatched events
        $this->events->listen('*', function ($eventName, $payload): void {
            $event = $payload[0] ?? null;
            if ($event) {
                $this->dispatchedEvents[] = $event;
            }
        });

        $this->cart = new Cart(
            identifier: 'test_cart',
            storage: new InMemoryStorage,
            events: $this->events,
            instanceName: 'test_cart',
            eventsEnabled: true
        );
    });

    it('dispatches cart created event', function (): void {
        // CartCreated fires when first item is added
        $this->cart->add('product-1', 'Test Product', 10.00, 1);

        $cartCreatedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartCreated);

        expect($cartCreatedEvents)->toHaveCount(1);

        $event = reset($cartCreatedEvents);
        expect($event->cart)->toBeInstanceOf(Cart::class);
    });

    it('dispatches item added event', function (): void {
        $this->cart->add('product-1', 'Test Product', 10.00, 2);

        $itemAddedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof ItemAdded);

        expect($itemAddedEvents)->toHaveCount(1);

        $event = reset($itemAddedEvents);
        expect($event->item->id)->toBe('product-1');
        expect($event->item->name)->toBe('Test Product');
        expect($event->item->price)->toBe(1000);  // 10.00 as cents
        expect($event->item->quantity)->toBe(2);
    });

    it('dispatches condition added event for cart level conditions', function (): void {
        $this->cart->addDiscount('summer_sale', '-20%');

        $conditionAddedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionAdded);

        expect($conditionAddedEvents)->toHaveCount(1);

        $event = reset($conditionAddedEvents);
        expect($event->condition->getName())->toBe('summer_sale');
        expect($event->condition->getType())->toBe('discount');
    });

    it('dispatches condition removed event for cart level conditions', function (): void {
        $this->cart->addDiscount('summer_sale', '-20%');

        // Clear events from add
        $this->dispatchedEvents = [];

        $this->cart->removeCondition('summer_sale');

        $conditionRemovedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionRemoved);

        expect($conditionRemovedEvents)->toHaveCount(1);

        $event = reset($conditionRemovedEvents);
        expect($event->condition->getName())->toBe('summer_sale');
    });

    it('calculates correct impact for condition added event', function (): void {
        $this->cart->add('product-1', 'Test Product', 100.00, 1);

        // Clear events from add
        $this->dispatchedEvents = [];

        $this->cart->addDiscount('big_discount', '-30%');

        $conditionAddedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionAdded);
        $event = reset($conditionAddedEvents);

        // Impact is calculated on current subtotal which includes the discount
        // Subtotal after discount: 70.00 = 7000 cents, so impact = -30% of 7000 = -2100
        expect($event->getConditionImpact())->toBe(-2100.0);
    });

    it('calculates lost savings for condition removed event', function (): void {
        $this->cart->add('product-1', 'Test Product', 100.00, 1);
        $this->cart->addDiscount('savings_discount', '-25%');

        // Clear events from setup
        $this->dispatchedEvents = [];

        $this->cart->removeCondition('savings_discount');

        $conditionRemovedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionRemoved);
        $event = reset($conditionRemovedEvents);

        expect($event->getLostSavings())->toBe(2500.0);  // 25% of 10000 cents = 2500 (positive)
    });

    it('does not dispatch condition events when events are disabled', function (): void {
        $cartWithoutEvents = new Cart(
            identifier: 'no_events_cart',
            storage: new InMemoryStorage,
            events: new Dispatcher,
            instanceName: 'no_events_cart',
            eventsEnabled: false
        );

        $eventsCount = count($this->dispatchedEvents);

        $cartWithoutEvents->add('product-1', 'Test Product', 100.00, 1);
        $cartWithoutEvents->addDiscount('test_discount', '-10%');
        $cartWithoutEvents->removeCondition('test_discount');

        // Should not have dispatched any new events
        expect($this->dispatchedEvents)->toHaveCount($eventsCount);
    });
});

// Additional standalone tests
beforeEach(function (): void {
    $this->events = new Dispatcher;
    $this->dispatchedEvents = [];

    // Set up event listeners to capture all dispatched events
    $this->events->listen('*', function ($eventName, $payload): void {
        $event = $payload[0] ?? null;
        if ($event) {
            $this->dispatchedEvents[] = $event;
        }
    });

    $this->cart = new Cart(
        identifier: 'test_cart',
        storage: new InMemoryStorage,
        events: $this->events,
        instanceName: 'test_cart',
        eventsEnabled: true
    );
});

it('provides comprehensive data in condition added event', function (): void {
    $this->cart->add('product-1', 'Test Product', 100.00, 1);

    // Clear events from add
    $this->dispatchedEvents = [];

    $this->cart->addDiscount('test_discount', '-15%');

    $conditionAddedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionAdded);
    $event = reset($conditionAddedEvents);

    $data = $event->toArray();

    expect($data)->toHaveKeys([
        'condition',
        'cart',
        'impact',
        'timestamp',
    ]);

    expect($data['condition']['name'])->toBe('test_discount');
    expect($data['condition']['type'])->toBe('discount');
    // Impact is calculated on current subtotal which includes this discount
    // Subtotal after -15%: 8500 cents (85.00), so impact = -15% of 8500 = -1275
    expect($data['impact'])->toBe(-1275.0);
});

it('provides comprehensive data in condition removed event', function (): void {
    $this->cart->add('product-1', 'Test Product', 100.00, 1);
    $this->cart->addDiscount('removal_test', '-20%');

    // Clear events from setup
    $this->dispatchedEvents = [];

    $this->cart->removeCondition('removal_test');

    $conditionRemovedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionRemoved);
    $event = reset($conditionRemovedEvents);

    $data = $event->toArray();

    expect($data)->toHaveKeys([
        'condition',
        'cart',
        'impact',
        'lost_savings',
        'reason',
        'timestamp',
    ]);

    expect($data['condition']['name'])->toBe('removal_test');
    expect($data['lost_savings'])->toBe(2000.0);  // 20% of 10000 cents (positive)
    expect($data['reason'])->toBeNull();
});

it('shows zero lost savings for non-discount removals', function (): void {
    $this->cart->add('product-1', 'Test Product', 100.00, 1);
    $this->cart->addShipping('sales_tax', 8.5);

    // Clear events from setup
    $this->dispatchedEvents = [];

    $this->cart->removeCondition('sales_tax');

    $conditionRemovedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionRemoved);
    $event = reset($conditionRemovedEvents);

    expect($event->getLostSavings())->toBe(0.0);
});

it('does not dispatch event when removing non-existent condition', function (): void {
    $this->cart->add('product-1', 'Test Product', 100.00, 1);

    // Clear events from setup
    $this->dispatchedEvents = [];

    $this->cart->removeCondition('non_existent_condition');

    $conditionRemovedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionRemoved);

    expect($conditionRemovedEvents)->toHaveCount(0);
});

it('works with helper methods for condition events', function (): void {
    $this->cart->add('product-1', 'Test Product', 100.00, 1);

    // Clear events from setup
    $this->dispatchedEvents = [];

    $this->cart->addTax('sales_tax', '8.25');
    $this->cart->addShipping('express', 15.00);
    $this->cart->addDiscount('loyalty', '-10%');

    $conditionAddedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof CartConditionAdded);

    expect($conditionAddedEvents)->toHaveCount(3);

    $types = array_map(fn ($event) => $event->condition->getType(), $conditionAddedEvents);
    expect($types)->toContain('tax', 'shipping', 'discount');
});
