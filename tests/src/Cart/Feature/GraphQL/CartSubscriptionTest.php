<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\GraphQL\Subscriptions\CartSubscription;

describe('CartSubscription Integration', function (): void {
    beforeEach(function (): void {
        $this->cartManager = app(CartManagerInterface::class);
        $this->subscription = new CartSubscription;
    });

    describe('constants', function (): void {
        it('defines cart updated events', function (): void {
            expect(CartSubscription::CART_UPDATED_EVENTS)->toContain(ItemAdded::class);
            expect(CartSubscription::CART_UPDATED_EVENTS)->toContain(ItemUpdated::class);
            expect(CartSubscription::CART_UPDATED_EVENTS)->toContain(ItemRemoved::class);
            expect(CartSubscription::CART_UPDATED_EVENTS)->toContain(CartCleared::class);
            expect(CartSubscription::CART_UPDATED_EVENTS)->toContain(CartConditionAdded::class);
            expect(CartSubscription::CART_UPDATED_EVENTS)->toContain(CartConditionRemoved::class);
        });

        it('defines cart item events', function (): void {
            expect(CartSubscription::CART_ITEM_EVENTS)->toContain(ItemAdded::class);
            expect(CartSubscription::CART_ITEM_EVENTS)->toContain(ItemUpdated::class);
            expect(CartSubscription::CART_ITEM_EVENTS)->toContain(ItemRemoved::class);
        });
    });

    describe('sdl', function (): void {
        it('returns valid GraphQL subscription schema', function (): void {
            $sdl = CartSubscription::sdl();

            expect($sdl)
                ->toContain('extend type Subscription')
                ->toContain('cartUpdated')
                ->toContain('cartItemChanged')
                ->toContain('cartConditionChanged')
                ->toContain('checkoutStatusUpdated')
                ->toContain('CartUpdatePayload')
                ->toContain('CartItemChangePayload')
                ->toContain('CartConditionChangePayload')
                ->toContain('CheckoutStatusPayload');
        });

        it('defines event type enums', function (): void {
            $sdl = CartSubscription::sdl();

            expect($sdl)
                ->toContain('enum CartEventType')
                ->toContain('ITEM_ADDED')
                ->toContain('ITEM_REMOVED')
                ->toContain('CART_CLEARED')
                ->toContain('enum CartItemEventType')
                ->toContain('enum CartConditionEventType')
                ->toContain('enum CheckoutStatus');
        });

        it('defines cartUpdated subscription', function (): void {
            $sdl = CartSubscription::sdl();

            expect($sdl)->toContain('cartUpdated(identifier: String!, instance: String = "default"): CartUpdatePayload!');
        });

        it('defines checkout status enum values', function (): void {
            $sdl = CartSubscription::sdl();

            expect($sdl)
                ->toContain('PENDING')
                ->toContain('VALIDATING')
                ->toContain('PROCESSING_PAYMENT')
                ->toContain('FULFILLING')
                ->toContain('COMPLETED')
                ->toContain('FAILED')
                ->toContain('CANCELLED');
        });
    });

    describe('authorizeCartUpdated', function (): void {
        it('returns true for authorization', function (): void {
            $result = $this->subscription->authorizeCartUpdated(null, ['identifier' => 'test']);

            expect($result)->toBeTrue();
        });
    });

    describe('filterCartUpdated', function (): void {
        it('filters by identifier and instance using real cart event', function (): void {
            $identifier = 'subscription-filter-test-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $item = $cart->getItems()->first();
            // ItemAdded takes (CartItem $item, Cart $cart)
            $event = new ItemAdded($item, $cart);

            $result = $this->subscription->filterCartUpdated($event, [
                'identifier' => $identifier,
                'instance' => 'default',
            ]);

            expect($result)->toBeTrue();
        });

        it('returns false for non-matching identifier', function (): void {
            $identifier = 'subscription-filter-nomatch-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $item = $cart->getItems()->first();
            $event = new ItemAdded($item, $cart);

            $result = $this->subscription->filterCartUpdated($event, [
                'identifier' => 'different-identifier',
                'instance' => 'default',
            ]);

            expect($result)->toBeFalse();
        });

        it('returns false when event has no cart', function (): void {
            $event = new class {
                // No cart property
            };

            $result = $this->subscription->filterCartUpdated($event, [
                'identifier' => 'test',
                'instance' => 'default',
            ]);

            expect($result)->toBeFalse();
        });
    });

    describe('filterCartItemChanged', function (): void {
        it('filters item events by identifier', function (): void {
            $identifier = 'subscription-item-filter-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $item = $cart->getItems()->first();
            $event = new ItemAdded($item, $cart);

            $result = $this->subscription->filterCartItemChanged($event, [
                'identifier' => $identifier,
                'instance' => 'default',
            ]);

            expect($result)->toBeTrue();
        });

        it('returns false for non-item event', function (): void {
            $identifier = 'subscription-non-item-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();

            $event = new CartCleared($cart);

            $result = $this->subscription->filterCartItemChanged($event, [
                'identifier' => $identifier,
                'instance' => 'default',
            ]);

            expect($result)->toBeFalse();
        });
    });

    describe('resolveCartUpdated', function (): void {
        it('transforms ItemAdded event to payload', function (): void {
            $identifier = 'subscription-resolve-add-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $item = $cart->getItems()->first();
            $event = new ItemAdded($item, $cart);

            $payload = $this->subscription->resolveCartUpdated($event);

            expect($payload)->toHaveKey('event');
            expect($payload)->toHaveKey('cart');
            expect($payload)->toHaveKey('timestamp');
            expect($payload)->toHaveKey('metadata');
            expect($payload['event'])->toBe('ITEM_ADDED');
        });

        it('transforms ItemRemoved event to payload', function (): void {
            $identifier = 'subscription-resolve-remove-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $item = $cart->getItems()->first();
            $cart->remove('item-1');

            $event = new ItemRemoved($item, $cart);

            $payload = $this->subscription->resolveCartUpdated($event);

            expect($payload['event'])->toBe('ITEM_REMOVED');
        });

        it('transforms CartCleared event to payload', function (): void {
            $identifier = 'subscription-resolve-clear-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);
            $cart->clear();

            $event = new CartCleared($cart);

            $payload = $this->subscription->resolveCartUpdated($event);

            expect($payload['event'])->toBe('CART_CLEARED');
        });
    });

    describe('resolveCartItemChanged', function (): void {
        it('transforms item event to item change payload', function (): void {
            $identifier = 'subscription-item-resolve-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 2);

            $item = $cart->getItems()->first();
            $event = new ItemAdded($item, $cart);

            $payload = $this->subscription->resolveCartItemChanged($event);

            expect($payload)->toHaveKey('event');
            expect($payload)->toHaveKey('item');
            expect($payload)->toHaveKey('cart');
            expect($payload)->toHaveKey('timestamp');
            expect($payload['event'])->toBe('ADDED');
        });

        it('transforms ItemUpdated event correctly', function (): void {
            $identifier = 'subscription-item-update-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);
            $cart->update('item-1', ['quantity' => 5]);

            $item = $cart->getItems()->first();
            $event = new ItemUpdated($item, $cart);

            $payload = $this->subscription->resolveCartItemChanged($event);

            expect($payload['event'])->toBe('UPDATED');
        });

        it('transforms ItemRemoved event correctly', function (): void {
            $identifier = 'subscription-item-remove-' . uniqid();

            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $item = $cart->getItems()->first();
            $cart->remove('item-1');

            $event = new ItemRemoved($item, $cart);

            $payload = $this->subscription->resolveCartItemChanged($event);

            expect($payload['event'])->toBe('REMOVED');
        });
    });

    describe('buildCheckoutStatusPayload', function (): void {
        it('builds complete checkout status payload', function (): void {
            $payload = $this->subscription->buildCheckoutStatusPayload(
                checkoutId: 'checkout-123',
                status: 'PROCESSING_PAYMENT',
                stage: 'payment_initiation',
                message: 'Initializing payment gateway',
                orderId: 'order-456',
                paymentUrl: 'https://payment.example.com/pay'
            );

            expect($payload)->toHaveKey('checkoutId');
            expect($payload)->toHaveKey('status');
            expect($payload)->toHaveKey('stage');
            expect($payload)->toHaveKey('message');
            expect($payload)->toHaveKey('orderId');
            expect($payload)->toHaveKey('paymentUrl');
            expect($payload)->toHaveKey('timestamp');

            expect($payload['checkoutId'])->toBe('checkout-123');
            expect($payload['status'])->toBe('PROCESSING_PAYMENT');
            expect($payload['orderId'])->toBe('order-456');
        });

        it('handles null optional parameters', function (): void {
            $payload = $this->subscription->buildCheckoutStatusPayload(
                checkoutId: 'checkout-789',
                status: 'PENDING'
            );

            expect($payload['stage'])->toBeNull();
            expect($payload['message'])->toBeNull();
            expect($payload['orderId'])->toBeNull();
            expect($payload['paymentUrl'])->toBeNull();
        });

        it('builds completed status', function (): void {
            $payload = $this->subscription->buildCheckoutStatusPayload(
                checkoutId: 'checkout-done',
                status: 'COMPLETED',
                orderId: 'order-completed-123'
            );

            expect($payload['status'])->toBe('COMPLETED');
            expect($payload['orderId'])->toBe('order-completed-123');
        });

        it('builds failed status with message', function (): void {
            $payload = $this->subscription->buildCheckoutStatusPayload(
                checkoutId: 'checkout-fail',
                status: 'FAILED',
                message: 'Payment declined'
            );

            expect($payload['status'])->toBe('FAILED');
            expect($payload['message'])->toBe('Payment declined');
        });
    });
});
