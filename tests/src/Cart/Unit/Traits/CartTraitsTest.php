<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Facades\Config;

describe('HasLazyPipeline trait', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'lazy-pipeline-test');
        $this->cart->add('item-1', 'Product 1', 1000, 2);
        $this->cart->add('item-2', 'Product 2', 500, 3);
    });

    it('lazy pipeline is enabled by default', function (): void {
        Config::set('cart.performance.lazy_pipeline', true);

        expect($this->cart->isLazyPipelineEnabled())->toBeTrue();
    });

    it('can disable lazy pipeline', function (): void {
        $result = $this->cart->withLazyPipeline(false);

        expect($result)->toBe($this->cart)
            ->and($this->cart->isLazyPipelineEnabled())->toBeFalse();
    });

    it('can use withoutLazyPipeline shortcut', function (): void {
        $result = $this->cart->withoutLazyPipeline();

        expect($result)->toBe($this->cart)
            ->and($this->cart->isLazyPipelineEnabled())->toBeFalse();
    });

    it('can re-enable lazy pipeline', function (): void {
        Config::set('cart.performance.lazy_pipeline', true);

        $this->cart->withLazyPipeline(false);
        $this->cart->withLazyPipeline(true);

        expect($this->cart->isLazyPipelineEnabled())->toBeTrue();
    });

    it('respects config setting when disabled globally', function (): void {
        Config::set('cart.performance.lazy_pipeline', false);

        expect($this->cart->isLazyPipelineEnabled())->toBeFalse();
    });

    it('returns pipeline cache stats when no cache exists', function (): void {
        $this->cart->invalidatePipelineCache();
        $stats = $this->cart->getPipelineCacheStats();

        expect($stats)->toBeArray()
            ->and($stats)->toHaveKeys(['cached_phases', 'is_stale', 'has_full_result'])
            ->and($stats['cached_phases'])->toBe(0)
            ->and($stats['is_stale'])->toBeTrue()
            ->and($stats['has_full_result'])->toBeFalse();
    });

    it('invalidates pipeline cache', function (): void {
        // Force cache creation by getting totals
        Config::set('cart.performance.lazy_pipeline', true);
        $this->cart->withLazyPipeline(true);
        $this->cart->getRawTotal();

        // Invalidate
        $this->cart->invalidatePipelineCache();
        $stats = $this->cart->getPipelineCacheStats();

        expect($stats['is_stale'])->toBeTrue();
    });

    it('calculates totals with lazy pipeline enabled', function (): void {
        Config::set('cart.performance.lazy_pipeline', true);
        $this->cart->withLazyPipeline(true);

        $subtotal = $this->cart->getRawSubtotal();
        $total = $this->cart->getRawTotal();

        expect($subtotal)->toBe(3500) // 1000*2 + 500*3
            ->and($total)->toBe(3500);
    });

    it('calculates totals with lazy pipeline disabled', function (): void {
        $this->cart->withLazyPipeline(false);

        $subtotal = $this->cart->getRawSubtotal();
        $total = $this->cart->getRawTotal();

        expect($subtotal)->toBe(3500)
            ->and($total)->toBe(3500);
    });

    it('calculates totals correctly with conditions', function (): void {
        Config::set('cart.performance.lazy_pipeline', true);
        $this->cart->withLazyPipeline(true);

        $this->cart->addCondition(new CartCondition(
            name: 'Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        ));

        $subtotal = $this->cart->getRawSubtotal();
        $total = $this->cart->getRawTotal();

        expect($subtotal)->toBe(3150) // 3500 - 10% = 3150
            ->and($total)->toBe(3150);
    });

    it('invalidates cache when cart changes', function (): void {
        Config::set('cart.performance.lazy_pipeline', true);
        $this->cart->withLazyPipeline(true);

        // Initial calculation
        $initialTotal = $this->cart->getRawTotal();
        expect($initialTotal)->toBe(3500);

        // Add item (should invalidate cache)
        $this->cart->add('item-3', 'Product 3', 200, 1);

        // Recalculate
        $newTotal = $this->cart->getRawTotal();
        expect($newTotal)->toBe(3700);
    });
});

describe('ProvidesConditionScopes trait', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'scopes-test');
        $this->cart->add('item-1', 'Product', 1000, 1);
    });

    it('can register shipment resolver and retrieve shipments', function (): void {
        $result = $this->cart->resolveShipmentsUsing(fn() => [
            ['id' => 'ship-1', 'amount' => 500],
            ['id' => 'ship-2', 'amount' => 800],
        ]);

        expect($result)->toBe($this->cart)
            ->and($this->cart->hasShipmentResolver())->toBeTrue();

        $shipments = iterator_to_array($this->cart->getShipments());
        expect($shipments)->toHaveCount(2)
            ->and($shipments[0]['amount'])->toBe(500);
    });

    it('returns empty shipments when resolver returns empty', function (): void {
        $this->cart->resolveShipmentsUsing(fn() => []);

        $shipments = iterator_to_array($this->cart->getShipments());

        expect($shipments)->toBeEmpty();
    });

    it('can register payment resolver and retrieve payments', function (): void {
        $result = $this->cart->resolvePaymentsUsing(fn() => [
            ['id' => 'credit-card', 'fee' => 300],
        ]);

        expect($result)->toBe($this->cart)
            ->and($this->cart->hasPaymentResolver())->toBeTrue();

        $payments = iterator_to_array($this->cart->getPayments());
        expect($payments)->toHaveCount(1)
            ->and($payments[0]['fee'])->toBe(300);
    });

    it('returns empty payments when resolver returns empty', function (): void {
        $this->cart->resolvePaymentsUsing(fn() => []);

        $payments = iterator_to_array($this->cart->getPayments());

        expect($payments)->toBeEmpty();
    });

    it('can register fulfillment resolver and retrieve fulfillments', function (): void {
        $result = $this->cart->resolveFulfillmentsUsing(fn() => [
            ['id' => 'delivery', 'base_amount' => 2000],
        ]);

        expect($result)->toBe($this->cart)
            ->and($this->cart->hasFulfillmentResolver())->toBeTrue();

        $fulfillments = iterator_to_array($this->cart->getFulfillments());
        expect($fulfillments)->toHaveCount(1)
            ->and($fulfillments[0]['base_amount'])->toBe(2000);
    });

    it('returns empty fulfillments when resolver returns empty', function (): void {
        $this->cart->resolveFulfillmentsUsing(fn() => []);

        $fulfillments = iterator_to_array($this->cart->getFulfillments());

        expect($fulfillments)->toBeEmpty();
    });

    it('shipment resolver receives cart instance', function (): void {
        $receivedCart = null;

        $this->cart->resolveShipmentsUsing(function ($cart) use (&$receivedCart) {
            $receivedCart = $cart;

            return [];
        });

        $this->cart->getShipments();

        expect($receivedCart)->toBe($this->cart);
    });

    it('payment resolver receives cart instance', function (): void {
        $receivedCart = null;

        $this->cart->resolvePaymentsUsing(function ($cart) use (&$receivedCart) {
            $receivedCart = $cart;

            return [];
        });

        $this->cart->getPayments();

        expect($receivedCart)->toBe($this->cart);
    });

    it('fulfillment resolver receives cart instance', function (): void {
        $receivedCart = null;

        $this->cart->resolveFulfillmentsUsing(function ($cart) use (&$receivedCart) {
            $receivedCart = $cart;

            return [];
        });

        $this->cart->getFulfillments();

        expect($receivedCart)->toBe($this->cart);
    });

    it('resolver can access cart items', function (): void {
        $this->cart->resolveShipmentsUsing(function ($cart) {
            $itemCount = $cart->countItems();

            return [
                ['id' => 'calculated', 'items' => $itemCount],
            ];
        });

        $shipments = iterator_to_array($this->cart->getShipments());

        expect($shipments[0]['items'])->toBe(1);
    });
});
