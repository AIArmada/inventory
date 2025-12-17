<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\FilamentCart\Services\RuleConverter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;

describe('RuleConverter', function (): void {

    // Helper to create a real Cart with mocked storage
    function createCartWithItems(array $itemsData)
    {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getItems')->andReturn($itemsData);
        $storage->shouldReceive('getConfigs')->andReturn([]);
        $storage->shouldReceive('getConditions')->andReturn([]);
        $storage->shouldReceive('getMetadata')->andReturn([]);
        $storage->shouldReceive('getId')->andReturn(null);
        $storage->shouldReceive('getVersion')->andReturn(null);
        $storage->shouldReceive('getCreatedAt')->andReturn(null);
        $storage->shouldReceive('getUpdatedAt')->andReturn(null);

        return new Cart($storage, 'session-123');
    }

    it('throws exception for unknown rule type', function (): void {
        expect(fn() => RuleConverter::convertRules(['unknown_rule' => 'value']))
            ->toThrow(InvalidArgumentException::class, 'Unknown rule type: unknown_rule');
    });

    it('converts min_total rule and executes it', function (): void {
        // Rule value in CENTS (10000 = $100.00) to match Cart's internal usage
        $rules = RuleConverter::convertRules(['min_total' => 10000]);
        $rule = $rules[0];

        // 1 item worth 15000 cents = $150.00
        $cartPass = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 15000, 'quantity' => 1]
        ]);

        // 1 item worth 5000 cents = $50.00
        $cartFail = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 5000, 'quantity' => 1]
        ]);

        expect($rule($cartPass))->toBeTrue();
        expect($rule($cartFail))->toBeFalse();
    });

    it('converts min_items rule and executes it', function (): void {
        $rules = RuleConverter::convertRules(['min_items' => 2]);
        $rule = $rules[0];

        $cartPass = createCartWithItems([
            ['id' => '1', 'name' => 'Item 1', 'price' => 100, 'quantity' => 1],
            ['id' => '2', 'name' => 'Item 2', 'price' => 100, 'quantity' => 1]
        ]);

        $cartFail = createCartWithItems([
            ['id' => '1', 'name' => 'Item 1', 'price' => 100, 'quantity' => 1]
        ]);

        expect($rule($cartPass))->toBeTrue();
        expect($rule($cartFail))->toBeFalse();
    });

    it('converts max_total rule and executes it', function (): void {
        // Rule value in CENTS
        $rules = RuleConverter::convertRules(['max_total' => 10000]); // $100.00
        $rule = $rules[0];

        // $50
        $cartPass = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 5000, 'quantity' => 1]
        ]);

        // $150
        $cartFail = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 15000, 'quantity' => 1]
        ]);

        expect($rule($cartPass))->toBeTrue();
        expect($rule($cartFail))->toBeFalse();
    });

    it('converts max_items rule and executes it', function (): void {
        $rules = RuleConverter::convertRules(['max_items' => 2]);
        $rule = $rules[0];

        $cartPass = createCartWithItems([
            ['id' => '1', 'name' => 'Item 1', 'price' => 100, 'quantity' => 1]
        ]);

        $cartFail = createCartWithItems([
            ['id' => '1', 'name' => 'Item 1', 'price' => 100, 'quantity' => 1],
            ['id' => '2', 'name' => 'Item 2', 'price' => 100, 'quantity' => 1],
            ['id' => '3', 'name' => 'Item 3', 'price' => 100, 'quantity' => 1]
        ]);

        expect($rule($cartPass))->toBeTrue();
        expect($rule($cartFail))->toBeFalse();
    });

    it('converts has_category rule and executes it', function (): void {
        $rules = RuleConverter::convertRules(['has_category' => 'electronics']);
        $rule = $rules[0];

        $cartPass = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 100, 'quantity' => 1, 'attributes' => ['category' => 'electronics']]
        ]);

        $cartFail = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 100, 'quantity' => 1, 'attributes' => ['category' => 'books']]
        ]);

        expect($rule($cartPass))->toBeTrue();
        expect($rule($cartFail))->toBeFalse();
    });

    it('converts user_vip rule and executes it', function (): void {
        $rules = RuleConverter::convertRules(['user_vip' => true]);
        $rule = $rules[0];

        $cart = createCartWithItems([]);

        // User is VIP
        $vipUser = new Illuminate\Foundation\Auth\User();
        $vipUser->is_vip = true;
        Auth::shouldReceive('user')->andReturn($vipUser);
        expect($rule($cart))->toBeTrue();
    });

    it('converts user_vip rule and executes it (fail case)', function (): void {
        $rules = RuleConverter::convertRules(['user_vip' => true]);
        $rule = $rules[0];
        $cart = createCartWithItems([]);

        // User is NOT VIP
        $regularUser = new Illuminate\Foundation\Auth\User();
        $regularUser->is_vip = false;
        Auth::shouldReceive('user')->andReturn($regularUser);

        expect($rule($cart))->toBeFalse();
    });

    it('converts specific_items rule and executes it', function (): void {
        $rules = RuleConverter::convertRules(['specific_items' => ['sku-123']]);
        $rule = $rules[0];

        // Access via SKU in attributes
        $cartPass = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 100, 'quantity' => 1, 'attributes' => ['sku' => 'sku-123']]
        ]);

        // Access via ID matching
        $cartPassId = createCartWithItems([
            ['id' => 'sku-123', 'name' => 'Item', 'price' => 100, 'quantity' => 1]
        ]);

        $cartFail = createCartWithItems([
            ['id' => '1', 'name' => 'Item', 'price' => 100, 'quantity' => 1, 'attributes' => ['sku' => 'sku-456']]
        ]);

        expect($rule($cartPass))->toBeTrue();
        expect($rule($cartPassId))->toBeTrue();
        expect($rule($cartFail))->toBeFalse();
    });

    it('converts item_quantity rule and executes it', function (): void {
        $rules = RuleConverter::convertRules(['item_quantity' => 5]);
        $rule = $rules[0];

        $cart = createCartWithItems([]);

        $itemPass = new CartItem('1', 'Item', 100, 6);
        $itemFail = new CartItem('1', 'Item', 100, 4);

        expect($rule($cart, $itemPass))->toBeTrue();
        expect($rule($cart, $itemFail))->toBeFalse();
    });

    it('converts item_price rule and executes it', function (): void {
        // Rule value in CENTS
        $rules = RuleConverter::convertRules(['item_price' => 5000]); // 5000 cents ($50.00)
        $rule = $rules[0];

        $cart = createCartWithItems([]);

        $itemPass = new CartItem('1', 'Item', 6000, 1); // 6000 cents ($60.00)
        $itemFail = new CartItem('1', 'Item', 4000, 1); // 4000 cents ($40.00)

        expect($rule($cart, $itemPass))->toBeTrue();
        expect($rule($cart, $itemFail))->toBeFalse();
    });
});
