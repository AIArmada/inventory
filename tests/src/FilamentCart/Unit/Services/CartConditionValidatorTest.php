<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\Condition as ConditionModel;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\FilamentCart\Services\CartConditionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('CartConditionValidator', function (): void {
    it('returns valid when no global conditions present', function (): void {
        $validator = new CartConditionValidator;
        $storage = new DatabaseStorage(
            database: DB::connection('testing'),
            table: 'carts',
        );

        $cart = new Cart(
            storage: $storage,
            identifier: 'user-' . Str::random(12),
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );

        ConditionModel::query()->delete();

        $cart->add('sku-1', 'Test Item', 10000, 1);

        $result = $validator->validateAndClean($cart);

        expect($result['is_valid'])->toBeTrue();
        expect($result['removed_conditions'])->toBeEmpty();
        expect($result['price_changed'])->toBeFalse();
    });

    it('removes deactivated global conditions', function (): void {
        $validator = new CartConditionValidator;
        $storage = new DatabaseStorage(
            database: DB::connection('testing'),
            table: 'carts',
        );

        $cart = new Cart(
            storage: $storage,
            identifier: 'user-' . Str::random(12),
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );

        ConditionModel::query()->delete();

        $cart->add('sku-1', 'Test Item', 10000, 1);
        $cart->addCondition([
            'name' => 'Deactivated Promo',
            'type' => 'discount',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10%',
            'attributes' => ['is_global' => true],
        ]);

        $result = $validator->validateAndClean($cart);

        expect($result['is_valid'])->toBeFalse();
        expect($result['removed_conditions'])->toContain('Deactivated Promo');
        expect($result['price_changed'])->toBeTrue();
        expect($cart->getConditions()->has('Deactivated Promo'))->toBeFalse();
    });

    it('keeps active global conditions', function (): void {
        $validator = new CartConditionValidator;
        $storage = new DatabaseStorage(
            database: DB::connection('testing'),
            table: 'carts',
        );

        $cart = new Cart(
            storage: $storage,
            identifier: 'user-' . Str::random(12),
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );

        ConditionModel::query()->delete();

        ConditionModel::create([
            'name' => 'Active Promo',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10%',
            'is_active' => true,
            'is_global' => true,
        ]);

        $cart->add('sku-1', 'Test Item', 10000, 1);
        $cart->addCondition([
            'name' => 'Active Promo',
            'type' => 'discount',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10%',
            'attributes' => ['is_global' => true],
        ]);

        $result = $validator->validateAndClean($cart);

        expect($result['is_valid'])->toBeTrue();
        expect($result['removed_conditions'])->toBeEmpty();
        expect($cart->getConditions()->has('Active Promo'))->toBeTrue();
    });
});
