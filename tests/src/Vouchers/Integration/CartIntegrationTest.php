<?php

declare(strict_types=1);

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Events\VoucherRemoved;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Support\CartManagerWithVouchers;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $manager = app(CartManager::class);

    if (! $manager instanceof CartManagerWithVouchers) {
        $proxy = CartManagerWithVouchers::fromCartManager($manager);

        app()->instance('cart', $proxy);
        app()->instance(CartManager::class, $proxy);
        Cart::clearResolvedInstance('cart');
    }

    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();

    VoucherModel::query()->forceDelete();

    config([
        'vouchers.cart.max_vouchers_per_cart' => 1,
        'vouchers.validation.check_user_limit' => false,
        'vouchers.validation.check_global_limit' => true,
        'vouchers.validation.check_min_cart_value' => true,
        'vouchers.code.case_sensitive' => true,
    ]);
});

test('can apply percentage voucher to cart', function (): void {
    VoucherModel::create([
        'name' => 'Test Voucher 10%',
        'code' => 'TEST10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-test-10', 'Test Product', 100);

    Cart::applyVoucher('TEST10');

    expect(Cart::hasVoucher('TEST10'))->toBeTrue()
        ->and(Cart::hasVoucher())->toBeTrue();
});

test('applying a voucher registers dynamic condition metadata', function (): void {
    VoucherModel::create([
        'name' => 'Metadata Voucher',
        'code' => 'META10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-meta', 'Test Product', 100);

    Cart::applyVoucher('META10');

    $cart = Cart::getCurrentCart();

    expect($cart->getDynamicConditions()->has('voucher_META10'))->toBeTrue();

    $metadata = $cart->getDynamicConditionMetadata();

    expect($metadata)->toHaveKey('voucher_META10')
        ->and($metadata['voucher_META10']['rule_factory_key'])->toBe(VoucherCondition::RULE_FACTORY_KEY)
        ->and($metadata['voucher_META10']['attributes']['voucher_code'])->toBe('META10');
});

test('voucher dynamic condition restores on new cart instance', function (): void {
    VoucherModel::create([
        'name' => 'Restore Voucher',
        'code' => 'RESTORE10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-restore', 'Test Product', 120);
    Cart::applyVoucher('RESTORE10');

    /** @var CartManager $manager */
    $manager = app(CartManager::class);
    $newCart = $manager->getCartInstance('default');

    expect($newCart->getDynamicConditions()->has('voucher_RESTORE10'))->toBeTrue();
});

test('removing a voucher clears dynamic metadata', function (): void {
    VoucherModel::create([
        'name' => 'Removable Voucher',
        'code' => 'CLEANUP10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-cleanup', 'Test Product', 150);
    Cart::applyVoucher('CLEANUP10');

    Cart::removeVoucher('CLEANUP10');

    $cart = Cart::getCurrentCart();

    expect($cart->getDynamicConditions()->has('voucher_CLEANUP10'))->toBeFalse()
        ->and($cart->getDynamicConditionMetadata())->not->toHaveKey('voucher_CLEANUP10');
});

test('can apply fixed amount voucher to cart', function (): void {
    VoucherModel::create([
        'name' => 'Test Voucher 20 Off',
        'code' => 'SAVE20',
        'type' => VoucherType::Fixed,
        'value' => 20,
        'currency' => 'MYR',
        'description' => '$20 off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-save-20', 'Test Product', 50);

    Cart::applyVoucher('SAVE20');

    expect(Cart::hasVoucher('SAVE20'))->toBeTrue();
});

test('throws exception when applying invalid voucher', function (): void {
    Cart::add('sku-invalid', 'Test Product', 25.00, 1);

    Cart::applyVoucher('INVALID');
})->throws(InvalidVoucherException::class);

test('throws exception when applying expired voucher', function (): void {
    VoucherModel::create([
        'name' => 'Expired Voucher',
        'code' => 'EXPIRED',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subMonth(),
        'expires_at' => now()->subDay(),
    ]);

    Cart::add('sku-expired', 'Test Product', 120.00, 1);

    Cart::applyVoucher('EXPIRED');
})->throws(InvalidVoucherException::class);

test('throws exception when applying voucher below minimum cart value', function (): void {
    VoucherModel::create([
        'name' => 'Min Cart Voucher',
        'code' => 'MIN100',
        'type' => VoucherType::Fixed,
        'status' => VoucherStatus::Active,
        'value' => 2000, // 2000 cents = $20.00
        'currency' => 'MYR',
        'min_cart_value' => 10000, // 10000 cents = $100.00
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-min-100', 'Test Product', 5000, 1); // 5000 cents = $50.00

    Cart::applyVoucher('MIN100');
})->throws(InvalidVoucherException::class);

test('can remove voucher from cart', function (): void {
    VoucherModel::create([
        'name' => 'Removable Voucher',
        'code' => 'REMOVE',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-remove', 'Test Product', 150.00, 1);

    Cart::applyVoucher('REMOVE');
    expect(Cart::hasVoucher('REMOVE'))->toBeTrue();

    Cart::removeVoucher('REMOVE');
    expect(Cart::hasVoucher('REMOVE'))->toBeFalse();
});

test('can clear all vouchers from cart', function (): void {
    VoucherModel::create([
        'name' => 'First Voucher',
        'code' => 'FIRST',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    VoucherModel::create([
        'name' => 'Second Voucher',
        'code' => 'SECOND',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 5,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    config(['vouchers.cart.max_vouchers_per_cart' => 2]);

    Cart::add('sku-multi', 'Test Product', 250.00, 1);

    Cart::applyVoucher('FIRST');
    Cart::applyVoucher('SECOND');

    expect(Cart::hasVoucher())->toBeTrue()
        ->and(count(Cart::getAppliedVouchers()))->toBe(2);

    Cart::clearVouchers();

    expect(Cart::hasVoucher())->toBeFalse()
        ->and(count(Cart::getAppliedVouchers()))->toBe(0);
});

test('can get applied voucher codes', function (): void {
    VoucherModel::create([
        'name' => 'Codes Voucher',
        'code' => 'CODE1',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-code', 'Test Product', 80.00, 1);

    Cart::applyVoucher('CODE1');

    $codes = Cart::getAppliedVoucherCodes();

    expect($codes)->toBeArray()
        ->and($codes)->toContain('CODE1');
});

test('respects maximum vouchers per cart', function (): void {
    config(['vouchers.cart.max_vouchers_per_cart' => 1, 'vouchers.cart.replace_when_max_reached' => false]);

    VoucherModel::create([
        'name' => 'First Voucher',
        'code' => 'FIRST',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    VoucherModel::create([
        'name' => 'Second Voucher',
        'code' => 'SECOND',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 5,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-max', 'Test Product', 200.00, 1);

    Cart::applyVoucher('FIRST');

    // Should throw exception because max is 1
    Cart::applyVoucher('SECOND');
})->throws(InvalidVoucherException::class, 'Cart already has the maximum number of vouchers');

test('replaces voucher when max per cart and replacement enabled', function (): void {
    config(['vouchers.cart.max_vouchers_per_cart' => 1, 'vouchers.cart.replace_when_max_reached' => true]);

    VoucherModel::create([
        'name' => 'First Voucher',
        'code' => 'FIRST_REPL',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    VoucherModel::create([
        'name' => 'Second Voucher',
        'code' => 'SECOND_REPL',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 5,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-replace', 'Test Product', 200.00, 1);

    Cart::applyVoucher('FIRST_REPL');
    Cart::applyVoucher('SECOND_REPL');

    expect(Cart::hasVoucher('FIRST_REPL'))->toBeFalse()
        ->and(Cart::hasVoucher('SECOND_REPL'))->toBeTrue();
});

test('throws exception when applying same voucher twice', function (): void {
    VoucherModel::create([
        'name' => 'Duplicate Voucher',
        'code' => 'DUPLICATE',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-duplicate', 'Test Product', 90.00, 1);

    Cart::applyVoucher('DUPLICATE');
    Cart::applyVoucher('DUPLICATE'); // Should throw
})->throws(InvalidVoucherException::class, 'already applied');

test('can check if cart can add more vouchers', function (): void {
    config(['vouchers.cart.max_vouchers_per_cart' => 2]);

    VoucherModel::create([
        'name' => 'Check Voucher',
        'code' => 'CHECK',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-check', 'Test Product', 250.00, 1);

    expect(Cart::canAddVoucher())->toBeTrue();

    Cart::applyVoucher('CHECK');

    VoucherModel::create([
        'name' => 'Second Check Voucher',
        'code' => 'CHECK2',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 5,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    expect(Cart::canAddVoucher())->toBeTrue();

    Cart::applyVoucher('CHECK2');

    expect(Cart::canAddVoucher())->toBeFalse();
});

test('dispatches voucher applied event', function (): void {
    Event::fake([VoucherApplied::class]);

    VoucherModel::create([
        'name' => 'Event Voucher',
        'code' => 'EVENT',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-event', 'Test Product', 140.00, 1);

    Cart::applyVoucher('EVENT');

    Event::assertDispatched(VoucherApplied::class);
});

test('dispatches voucher removed event', function (): void {
    Event::fake([VoucherRemoved::class]);

    VoucherModel::create([
        'name' => 'Remove Event Voucher',
        'code' => 'REMOVE_EVENT',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-remove-event', 'Test Product', 160.00, 1);

    Cart::applyVoucher('REMOVE_EVENT');
    Cart::removeVoucher('REMOVE_EVENT');

    Event::assertDispatched(VoucherRemoved::class);
});

test('voucher with case insensitive code works', function (): void {
    config(['vouchers.code.case_sensitive' => false]);

    VoucherModel::create([
        'name' => 'Lowercase Voucher',
        'code' => 'LOWERCASE',
        'type' => VoucherType::Percentage,
        'status' => VoucherStatus::Active,
        'value' => 10,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-lowercase', 'Test Product', 110.00, 1);

    Cart::applyVoucher('lowercase');

    expect(Cart::hasVoucher('LOWERCASE'))->toBeTrue()
        ->and(Cart::hasVoucher())->toBeTrue();
});

test('free shipping voucher is identified correctly', function (): void {
    VoucherModel::create([
        'name' => 'Free Shipping Voucher',
        'code' => 'FREESHIP',
        'type' => VoucherType::FreeShipping,
        'status' => VoucherStatus::Active,
        'value' => 0,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-free-shipping', 'Test Product', 130.00, 1);

    Cart::applyVoucher('FREESHIP');

    $voucher = Cart::getVoucherCondition('FREESHIP');

    expect($voucher)->not->toBeNull()
        ->and($voucher->isFreeShipping())->toBeTrue();
});
