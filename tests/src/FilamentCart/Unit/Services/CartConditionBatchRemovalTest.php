<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\FilamentCart\Models\Cart as CartModel;
use AIArmada\FilamentCart\Models\CartCondition as CartConditionModel;
use AIArmada\FilamentCart\Services\CartConditionBatchRemoval;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentCart\Services\CartSyncManager;

describe('CartConditionBatchRemoval service', function (): void {
    it('removes condition from affected carts', function (): void {
        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
        ]);

        // Create Real Cart with Mocked Storage
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getConditions')->andReturn([
            'Bad Condition' => [
                'name' => 'Bad Condition',
                'type' => 'coupon',
                'target' => 'cart.subtotal',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10'
            ]
        ]);
        $storage->shouldReceive('getItems')->andReturn([]);
        $storage->shouldReceive('getId')->andReturn('cart-id');
        $storage->shouldReceive('getVersion')->andReturn(1);
        $storage->shouldReceive('getCreatedAt')->andReturn(now()->toIso8601String());
        $storage->shouldReceive('getUpdatedAt')->andReturn(now()->toIso8601String());

        $storage->shouldReceive('putConditions')->with('session-123', 'default', \Mockery::on(function ($args) {
            return !isset($args['Bad Condition']);
        }))->once();

        $realCart = new Cart($storage, 'session-123');

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $cartManager->shouldReceive('resolve')
            ->with('default', 'session-123')
            ->andReturn($realCart);

        $syncManager = Mockery::mock(CartSyncManager::class);
        $syncManager->shouldReceive('sync')->with($realCart)->once();

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts('Bad Condition');

        expect($result['success'])->toBeTrue();
        expect($result['carts_processed'])->toBe(1);
        expect($result['carts_updated'])->toBe(1);
    });

    it('handles cart loading failures', function (): void {
        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-bad',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
        ]);

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $cartManager->shouldReceive('resolve')->andThrow(new Exception('Fail'));

        $syncManager = Mockery::mock(CartSyncManager::class);

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts('Bad Condition');

        expect($result['success'])->toBeTrue();
        expect($result['carts_processed'])->toBe(1);
        expect($result['carts_updated'])->toBe(0);
        expect($result['errors'])->not->toBeEmpty();
    });

    it('returns 0 processed if no carts match', function (): void {
        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-ok',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Good Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
        ]);

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $syncManager = Mockery::mock(CartSyncManager::class);

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts('Bad Condition');

        expect($result['carts_processed'])->toBe(0);
    });
});
