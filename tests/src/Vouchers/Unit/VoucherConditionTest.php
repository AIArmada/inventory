<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

it('converts voucher condition to cart condition and back', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST10',
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 75, dynamic: false);

    $cartCondition = $voucherCondition->toCartCondition();

    expect($cartCondition)->toBeInstanceOf(CartCondition::class)
        ->and($cartCondition->getType())->toBe('voucher')
        ->and($cartCondition->getAttributes()['voucher_code'] ?? null)->toBe('TEST10')
        ->and($cartCondition->getAttributes()['voucher_data']['code'] ?? null)->toBe('TEST10');

    $rehydrated = VoucherCondition::fromCartCondition($cartCondition);

    expect($rehydrated)->toBeInstanceOf(VoucherCondition::class);

    /** @var VoucherCondition $rehydratedVoucher */
    $rehydratedVoucher = $rehydrated;

    expect($rehydratedVoucher->getVoucherCode())->toBe('TEST10')
        ->and($rehydratedVoucher->getOrder())->toBe($cartCondition->getOrder());
});

it('exposes structured target definitions for vouchers', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 11,
        'code' => 'META10',
        'name' => 'Metadata Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 1500,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
    ]);

    $voucherCondition = new VoucherCondition($voucherData, dynamic: false);

    $cartCondition = $voucherCondition->toCartCondition();
    $target = $cartCondition->getTargetDefinition();

    expect($target->scope->value)->toBe('cart')
        ->and($target->phase->value)->toBe('cart_subtotal')
        ->and($target->application->value)->toBe('aggregate');

    $snapshot = $voucherCondition->toArray();

    expect($snapshot['target_definition'])->toBeArray()
        ->and($snapshot['target_definition']['scope'])->toBe('cart')
        ->and($snapshot['target_definition']['phase'])->toBe('cart_subtotal')
        ->and($snapshot['target_definition']['application'])->toBe('aggregate');
});

it('supports overriding the target definition via voucher metadata', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 22,
        'code' => 'ITEMSVOUCHER',
        'name' => 'Items Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 5,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
        'target_definition' => conditionTargetDefinition('items@item_discount/per-item'),
    ]);

    $voucherCondition = new VoucherCondition($voucherData, dynamic: false);

    $target = $voucherCondition->toCartCondition()->getTargetDefinition();

    expect($target->scope->value)->toBe('items')
        ->and($target->phase->value)->toBe('item_discount')
        ->and($target->application->value)->toBe('per-item');
});
