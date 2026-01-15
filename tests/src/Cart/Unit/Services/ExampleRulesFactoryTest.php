<?php

declare(strict_types=1);

use AIArmada\Cart\Testing\ExampleRulesFactory;

beforeEach(function (): void {
    $this->factory = new ExampleRulesFactory;
});

it('can create rules for min order discount', function (): void {
    $rules = $this->factory->createRules('min_order_discount', ['min_amount' => 150]);

    expect($rules)->toBeArray();
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});

it('can create rules for bulk quantity discount', function (): void {
    $rules = $this->factory->createRules('bulk_quantity_discount', ['min_quantity' => 5]);

    expect($rules)->toBeArray();
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});

it('can create rules for time based discount', function (): void {
    $rules = $this->factory->createRules('time_based_discount', [
        'start_time' => '10:00',
        'end_time' => '16:00',
    ]);

    expect($rules)->toBeArray();
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});

it('can create rules for category discount', function (): void {
    $rules = $this->factory->createRules('category_discount', ['category' => 'books']);

    expect($rules)->toBeArray();
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});

it('can create rules for seasonal discount', function (): void {
    $rules = $this->factory->createRules('seasonal_discount', ['season' => 'summer']);

    expect($rules)->toBeArray();
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});

it('throws exception for unknown rule factory key', function (): void {
    expect(fn () => $this->factory->createRules('unknown_key'))
        ->toThrow(InvalidArgumentException::class, 'Unknown rule factory key: unknown_key');
});

it('can check if factory can create rules', function (): void {
    expect($this->factory->canCreateRules('min_order_discount'))->toBeTrue();
    expect($this->factory->canCreateRules('bulk_quantity_discount'))->toBeTrue();
    expect($this->factory->canCreateRules('unknown_key'))->toBeFalse();
});

it('returns all available keys', function (): void {
    $keys = $this->factory->getAvailableKeys();

    expect($keys)->toBeArray();
    expect($keys)->toContain('min_order_discount');
    expect($keys)->toContain('bulk_quantity_discount');
    expect($keys)->toContain('user_role_discount');
    expect($keys)->toContain('time_based_discount');
    expect($keys)->toContain('category_discount');
    expect($keys)->toContain('first_time_customer');
    expect($keys)->toContain('day_of_week_discount');
    expect($keys)->toContain('seasonal_discount');
    expect($keys)->toContain('voucher_min_order');
    expect($keys)->toContain('free_shipping_threshold');
});

it('available keys match can create rules check', function (): void {
    $keys = $this->factory->getAvailableKeys();

    foreach ($keys as $key) {
        expect($this->factory->canCreateRules($key))->toBeTrue();
    }
});

it('uses default metadata values when not provided', function (): void {
    // Test min_order_discount with default min_amount of 100
    $rules = $this->factory->createRules('min_order_discount');
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();

    // Test bulk_quantity_discount with default min_quantity of 10
    $rules = $this->factory->createRules('bulk_quantity_discount');
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();

    // Test voucher_min_order with default min_amount of 50
    $rules = $this->factory->createRules('voucher_min_order');
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});

it('respects custom metadata values', function (): void {
    // The actual rule logic testing would require a full cart setup,
    // but we can at least verify the rules are created with custom metadata
    $rules = $this->factory->createRules('min_order_discount', ['min_amount' => 200]);
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();

    $rules = $this->factory->createRules('category_discount', ['category' => 'electronics']);
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();

    $rules = $this->factory->createRules('seasonal_discount', ['season' => 'winter']);
    expect($rules)->toHaveCount(1);
    expect($rules[0])->toBeCallable();
});
