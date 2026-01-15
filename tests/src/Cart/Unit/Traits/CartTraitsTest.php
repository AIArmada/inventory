<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use Tests\Support\Cart\InMemoryStorage;
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
