<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Commerce\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in(
    'src/Cart',
    'src/Chip',
    'src/Docs',
    'src/FilamentCart',
    'src/FilamentChip',
    'src/FilamentAuthz',
    'src/FilamentAffiliates',
    'src/Jnt',
    'src/Stock',
    'src/Affiliates',
    'src/Vouchers',
);

pest()->extend(InventoryTestCase::class)->in('src/Inventory');

// CashierChip tests use their own CashierChipTestCase via uses() in each test file
// Cashier (unified) tests use their own CashierTestCase via uses() in each test file

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

// expect()->extend('toBeCartable', function () {
//     return $this->toBeInstanceOf(AIArmada\Cart\Contracts\CartableInterface::class);
// });

expect()->extend('toHaveValidCartStructure', function () {
    return $this->toHaveKeys(['items', 'conditions', 'metadata']);
});

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

function createSampleCartData(): array
{
    return [
        [
            'id' => 'test-product-1',
            'name' => 'Test Product 1',
            'price' => 99.99,
            'quantity' => 2,
            'attributes' => ['color' => 'red', 'size' => 'large'],
        ],
        [
            'id' => 'test-product-2',
            'name' => 'Test Product 2',
            'price' => 149.99,
            'quantity' => 1,
            'attributes' => ['brand' => 'TestBrand'],
        ],
    ];
}

function createSampleConditionData(): array
{
    return [
        'discount' => [
            'name' => 'Test Discount',
            'type' => 'discount',
            'target' => 'cart@grand_total/aggregate',
            'target_definition' => ConditionTarget::from('cart@grand_total/aggregate')->toArray(),
            'value' => '-10%',
        ],
        'tax' => [
            'name' => 'Test Tax',
            'type' => 'tax',
            'target' => 'cart@grand_total/aggregate',
            'target_definition' => ConditionTarget::from('cart@grand_total/aggregate')->toArray(),
            'value' => '+8.5%',
        ],
        'shipping' => [
            'name' => 'Test Shipping',
            'type' => 'shipping',
            'target' => 'cart@shipping/aggregate',
            'target_definition' => ConditionTarget::from('cart@shipping/aggregate')->toArray(),
            'value' => '+15.00',
        ],
    ];
}

function conditionTargetDefinition(string $dsl): array
{
    return ConditionTarget::from($dsl)->toArray();
}
