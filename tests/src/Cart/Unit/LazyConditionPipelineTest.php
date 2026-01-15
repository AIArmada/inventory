<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Conditions\Pipeline\LazyConditionPipeline;
use Tests\Support\Cart\InMemoryStorage;

it('returns correct subtotal with lazy evaluation', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    expect($pipeline->getSubtotal())->toBe(2000);
});

it('returns correct total with lazy evaluation', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    expect($pipeline->getTotal())->toBe(2000);
});

it('memoizes subtotal results', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    // First call computes
    $subtotal1 = $pipeline->getSubtotal();

    // Second call should use cache
    $subtotal2 = $pipeline->getSubtotal();

    expect($subtotal1)->toBe(2000);
    expect($subtotal2)->toBe(2000);
    expect($pipeline->isCached())->toBeTrue();
});

it('invalidates cache when called', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    // Compute and cache
    $pipeline->getSubtotal();
    expect($pipeline->isCached())->toBeTrue();

    // Invalidate
    $pipeline->invalidate();
    expect($pipeline->isCached())->toBeFalse();
});

it('provides cache statistics', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    // Initial state
    $stats = $pipeline->getCacheStats();
    expect($stats['cached_phases'])->toBe(0);
    expect($stats['is_stale'])->toBeTrue();
    expect($stats['has_full_result'])->toBeFalse();

    // After subtotal computation
    $pipeline->getSubtotal();
    $stats = $pipeline->getCacheStats();
    expect($stats['cached_phases'])->toBeGreaterThan(0);
    expect($stats['is_stale'])->toBeFalse();
});

it('can get phase result for specific phase', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    $result = $pipeline->getPhaseResult(ConditionPhase::CART_SUBTOTAL);

    expect($result)->not->toBeNull();
    expect($result->phase)->toBe(ConditionPhase::CART_SUBTOTAL);
});

it('can get full pipeline result', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    $result = $pipeline->getFullResult();

    expect($result->initialAmount)->toBe(2000);
    expect($result->total())->toBe(2000);
});

it('reuses memoized phases when getting total after subtotal', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    // Get subtotal first
    $pipeline->getSubtotal();
    $statsAfterSubtotal = $pipeline->getCacheStats();

    // Get total - should reuse subtotal phases
    $pipeline->getTotal();
    $statsAfterTotal = $pipeline->getCacheStats();

    expect($statsAfterTotal['cached_phases'])->toBeGreaterThanOrEqual($statsAfterSubtotal['cached_phases']);
});

it('can be created from context with configuration', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-pipeline', events: null);
    $cart->add('item1', 'Product 1', 1000, 2);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = LazyConditionPipeline::fromContext($context, function ($p): void {
        // Custom configuration if needed
    });

    expect($pipeline->getTotal())->toBe(2000);
});

it('handles empty cart', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-lazy-empty', events: null);

    $context = ConditionPipelineContext::fromCart($cart);
    $pipeline = new LazyConditionPipeline($context);

    expect($pipeline->getSubtotal())->toBe(0);
    expect($pipeline->getTotal())->toBe(0);
});
