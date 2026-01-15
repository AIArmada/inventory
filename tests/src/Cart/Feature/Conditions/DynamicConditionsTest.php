<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
    $this->cart = new Cart($this->storage, 'dynamic_conditions_test');
});

it('can register a dynamic condition with rules', function (): void {
    $condition = new CartCondition(
        name: 'Big Spender Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 100,
        ]
    );

    $this->cart->registerDynamicCondition($condition);

    expect($this->cart->getDynamicConditions())->toHaveCount(1);
    expect($this->cart->getDynamicConditions()->first()->getName())->toBe('Big Spender Discount');
});

it('applies dynamic condition when rules are met', function (): void {
    // Register dynamic condition for orders over $100
    $condition = new CartCondition(
        name: 'Big Spender Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 100,
        ]
    );

    $this->cart->registerDynamicCondition($condition);

    // Add item worth $150 - should trigger the discount
    $this->cart->add('product-a', 'Product A', 150.00, 1);

    // Discount should be applied
    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->getConditions()->first()->getName())->toBe('Big Spender Discount');
    expect($this->cart->total()->getAmount())->toBe(13500); // 150 - 15 (10%)
});

it('prevents registering static conditions as dynamic', function (): void {
    $condition = new CartCondition(
        name: 'Static Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%'
        // No rules - this is a static condition
    );

    expect(fn () => $this->cart->registerDynamicCondition($condition))
        ->toThrow(InvalidArgumentException::class, 'Only dynamic conditions (with rules) can be registered.');
});

it('can register multiple dynamic conditions with different rules', function (): void {
    $volumeDiscount = new CartCondition(
        name: 'Volume Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-5%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->count() >= 3,
        ]
    );

    $bigSpenderDiscount = new CartCondition(
        name: 'Big Spender Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 100,
        ]
    );

    $this->cart->registerDynamicCondition($volumeDiscount);
    $this->cart->registerDynamicCondition($bigSpenderDiscount);

    expect($this->cart->getDynamicConditions())->toHaveCount(2);
});

it('requires ALL rules to be met before applying condition', function (): void {
    $strictDiscount = new CartCondition(
        name: 'Strict VIP Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-15%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 100, // Rule 1: Total > $100
            fn (Cart $cart) => $cart->getItems()->count() >= 3, // Rule 2: 3+ items
            fn (Cart $cart) => $cart->getItems()->sum('quantity') >= 5, // Rule 3: 5+ total quantity
        ]
    );

    $this->cart->registerDynamicCondition($strictDiscount);

    // Add 2 items totaling $120 - only satisfies rule 1
    $this->cart->add('product-a', 'Product A', 60.00, 2);
    $this->cart->add('product-b', 'Product B', 60.00, 1);

    // Should not apply discount (only 2 items, total quantity 3)
    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(18000); // 60*2 + 60*1 = 180

    // Add one more item to satisfy rule 2 (3+ items)
    $this->cart->add('product-c', 'Product C', 30.00, 1);

    // Still should not apply (total quantity only 4)
    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(21000); // 180 + 30 = 210

    // Update quantity to satisfy rule 3 (5+ total quantity) - use relative update
    $this->cart->update('product-c', ['quantity' => 1]); // adds 1 to current quantity of 1, making it 2

    // Now all rules are met - discount should apply
    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->getConditions()->first()->getName())->toBe('Strict VIP Discount');
    expect($this->cart->total()->getAmount())->toBe(20400); // 240 - 36 (15%)
});

it('applies condition when adding items triggers rules', function (): void {
    $volumeDiscount = new CartCondition(
        name: 'Volume Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-5%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->count() >= 3,
        ]
    );

    $this->cart->registerDynamicCondition($volumeDiscount);

    // Add 2 items - should not trigger
    $this->cart->add('product-a', 'Product A', 50.00, 1);
    $this->cart->add('product-b', 'Product B', 50.00, 1);

    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(10000);

    // Add 3rd item - should trigger discount
    $this->cart->add('product-c', 'Product C', 50.00, 1);

    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->total()->getAmount())->toBe(14250); // 150 - 7.5 (5%)
});

it('removes condition when removing items breaks rules', function (): void {
    $volumeDiscount = new CartCondition(
        name: 'Volume Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-5%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->count() >= 3,
        ]
    );

    $this->cart->registerDynamicCondition($volumeDiscount);

    // Add 3 items to trigger discount
    $this->cart->add('product-a', 'Product A', 50.00, 1);
    $this->cart->add('product-b', 'Product B', 50.00, 1);
    $this->cart->add('product-c', 'Product C', 50.00, 1);

    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->total()->getAmount())->toBe(14250);

    // Remove one item - should remove discount
    $this->cart->remove('product-c');

    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(10000);
});

it('updates condition when updating quantities affects rules', function (): void {
    $bigOrderDiscount = new CartCondition(
        name: 'Big Order Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->sum('quantity') >= 10,
        ]
    );

    $this->cart->registerDynamicCondition($bigOrderDiscount);

    // Add items with total quantity 8
    $this->cart->add('product-a', 'Product A', 25.00, 4);
    $this->cart->add('product-b', 'Product B', 25.00, 4);

    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(20000);

    // Update quantity to trigger discount - add 2 more to product-a (4+2=6, total becomes 10)
    $this->cart->update('product-a', ['quantity' => 2]);

    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->total()->getAmount())->toBe(22500); // 250 - 25 (10%)

    // Update quantity to remove discount - subtract 4 from product-a (6-4=2, total becomes 6)
    $this->cart->update('product-a', ['quantity' => -4]);

    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(15000); // 2*25 + 4*25 = 150
});

it('handles multiple dynamic conditions being applied simultaneously', function (): void {
    $volumeDiscount = new CartCondition(
        name: 'Volume Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-5%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->count() >= 3,
        ]
    );

    $bigSpenderDiscount = new CartCondition(
        name: 'Big Spender Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 200,
        ]
    );

    $this->cart->registerDynamicCondition($volumeDiscount);
    $this->cart->registerDynamicCondition($bigSpenderDiscount);

    // Add 3 items totaling $250 - should trigger both discounts
    $this->cart->add('product-a', 'Product A', 80.00, 1);
    $this->cart->add('product-b', 'Product B', 85.00, 1);
    $this->cart->add('product-c', 'Product C', 85.00, 1);

    $conditions = $this->cart->getConditions();
    $conditionNames = $conditions->map(fn ($condition) => $condition->getName())->toArray();

    expect($this->cart->getItems()->count())->toBe(3);
    expect($this->cart->getRawSubtotalWithoutConditions())->toBeGreaterThan(200);
    expect($conditions)->toHaveCount(2);
    expect($conditionNames)->toContain('Volume Discount');
    expect($conditionNames)->toContain('Big Spender Discount');

    // Both discounts should be applied: 250 * 0.95 * 0.90 = 213.75
    expect($this->cart->total()->getAmount())->toBe(21375);
});

it('works with item-level dynamic conditions', function (): void {
    $bulkItemDiscount = new CartCondition(
        name: 'Bulk Item Discount',
        type: 'discount',
        target: 'items@item_discount/per-item',
        value: '-20%',
        rules: [
            fn (Cart $cart, $item) => $item && $item->quantity >= 5,
        ]
    );

    $this->cart->registerDynamicCondition($bulkItemDiscount);

    // Add item with quantity 3 - should not trigger
    $this->cart->add('product-a', 'Product A', 20.00, 3);

    $item = $this->cart->get('product-a');
    expect($item->conditions)->toHaveCount(0);
    expect($item->getSubtotal()->getAmount())->toBe(6000);

    // Update quantity to 5 - use relative update (3 + 2 = 5)
    $this->cart->update('product-a', ['quantity' => 2]);

    $item = $this->cart->get('product-a');
    expect($item->conditions)->toHaveCount(1);
    expect($item->conditions->first()->getName())->toBe('Bulk Item Discount');
    expect($item->getSubtotal()->getAmount())->toBe(8000); // 5 * 20 * 0.8
});

it('removes dynamic condition from registry', function (): void {
    $discount = new CartCondition(
        name: 'Test Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 50,
        ]
    );

    $this->cart->registerDynamicCondition($discount);
    expect($this->cart->getDynamicConditions())->toHaveCount(1);

    // Add item to trigger condition
    $this->cart->add('product-a', 'Product A', 60.00, 1);
    expect($this->cart->getConditions())->toHaveCount(1);

    // Remove dynamic condition
    $this->cart->removeDynamicCondition('Test Discount');

    expect($this->cart->getDynamicConditions())->toHaveCount(0);
    expect($this->cart->getConditions())->toHaveCount(0); // Should also remove from active conditions
});

it('handles complex business logic rules', function (): void {
    // VIP customer discount: 15% off for orders > $100 with 3+ items and premium category
    $vipDiscount = new CartCondition(
        name: 'VIP Customer Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-15%',
        rules: [
            fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() > 100,
            fn (Cart $cart) => $cart->getItems()->count() >= 3,
            fn (Cart $cart) => $cart->getItems()->filter(
                fn ($item) => $item->getAttribute('category') === 'premium'
            )->count() >= 1,
        ]
    );

    $this->cart->registerDynamicCondition($vipDiscount);

    // Add regular items - should not trigger (no premium items)
    $this->cart->add('product-a', 'Product A', 50.00, 1, ['category' => 'regular']);
    $this->cart->add('product-b', 'Product B', 50.00, 1, ['category' => 'regular']);
    $this->cart->add('product-c', 'Product C', 50.00, 1, ['category' => 'regular']);

    expect($this->cart->getConditions())->toHaveCount(0);

    // Add a premium item - should trigger VIP discount
    $this->cart->add('premium-item', 'Premium Item', 50.00, 1, ['category' => 'premium']);

    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->getConditions()->first()->getName())->toBe('VIP Customer Discount');
    expect($this->cart->total()->getAmount())->toBe(17000); // 200 - 30 (15%)
});

it('handles edge case with removing item triggers conditions update', function (): void {
    $volumeDiscount = new CartCondition(
        name: 'Volume Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-5%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->count() >= 2,
        ]
    );

    $this->cart->registerDynamicCondition($volumeDiscount);

    // Add 2 items to trigger discount
    $this->cart->add('product-a', 'Product A', 50.00, 1);
    $this->cart->add('product-b', 'Product B', 50.00, 1);

    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->total()->getAmount())->toBe(9500); // 100 - 5%

    // Remove one item directly - should remove discount
    $this->cart->remove('product-b');

    expect($this->cart->getItems())->toHaveCount(1);
    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->total()->getAmount())->toBe(5000);
});

it('maintains dynamic conditions after clearing and re-adding items', function (): void {
    $volumeDiscount = new CartCondition(
        name: 'Volume Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-5%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->count() >= 2,
        ]
    );

    $this->cart->registerDynamicCondition($volumeDiscount);

    // Add items to trigger discount
    $this->cart->add('product-a', 'Product A', 50.00, 1);
    $this->cart->add('product-b', 'Product B', 50.00, 1);

    expect($this->cart->getConditions())->toHaveCount(1);

    // Clear cart
    $this->cart->clear();

    expect($this->cart->getItems())->toHaveCount(0);
    expect($this->cart->getConditions())->toHaveCount(0);
    expect($this->cart->getDynamicConditions())->toHaveCount(1); // Dynamic condition still registered

    // Re-add items - discount should re-apply
    $this->cart->add('product-c', 'Product C', 75.00, 1);
    $this->cart->add('product-d', 'Product D', 75.00, 1);

    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->getConditions()->first()->getName())->toBe('Volume Discount');
    expect($this->cart->total()->getAmount())->toBe(14250); // 150 - 5%
});

it('handles conditions with attribute-based rules', function (): void {
    $electronicsDiscount = new CartCondition(
        name: 'Electronics Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10%',
        rules: [
            fn (Cart $cart) => $cart->getItems()->filter(
                fn ($item) => $item->getAttribute('category') === 'electronics'
            )->count() >= 2,
        ]
    );

    $this->cart->registerDynamicCondition($electronicsDiscount);

    // Add one electronics item - should not trigger
    $this->cart->add('laptop', 'Laptop', 500.00, 1, ['category' => 'electronics']);
    expect($this->cart->getConditions())->toHaveCount(0);

    // Add another electronics item - should trigger
    $this->cart->add('phone', 'Phone', 300.00, 1, ['category' => 'electronics']);
    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->total()->getAmount())->toBe(72000); // 800 - 80 (10%)

    // Add non-electronics item - discount should remain
    $this->cart->add('book', 'Book', 20.00, 1, ['category' => 'books']);
    expect($this->cart->getConditions())->toHaveCount(1);
    expect($this->cart->total()->getAmount())->toBe(73800); // 820 - 82 (10%)
});
