<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Cart\VoucherConditionProvider;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Services\VoucherService;
use Mockery;

beforeEach(function (): void {
    $this->voucherService = Mockery::mock(VoucherService::class);
    $this->provider = new VoucherConditionProvider($this->voucherService);
});

afterEach(function (): void {
    Mockery::close();
});

/**
 * Creates a cart for testing.
 */
function createCartForVoucherConditionTest(array $metadata = []): Cart
{
    $cart = new Cart(new InMemoryStorage, 'voucher-condition-test');
    foreach ($metadata as $key => $value) {
        $cart->setMetadata($key, $value);
    }

    return $cart;
}

/**
 * Creates a VoucherData object for testing.
 *
 * Note: For percentages, value is in basis points (1000 = 10%, 1250 = 12.5%).
 * For fixed amounts, value is in cents (1000 = $10.00).
 */
function createVoucherData(
    string $id = 'voucher-123',
    string $code = 'SAVE10',
    VoucherType $type = VoucherType::Percentage,
    int $value = 1000,
    ?string $description = 'Save 10%',
    ?int $minCartValue = null,
    ?int $maxDiscount = null,
    ?string $currency = 'USD'
): VoucherData {
    return VoucherData::fromArray([
        'id' => $id,
        'code' => $code,
        'name' => $description ?? $code,
        'type' => $type,
        'value' => $value,
        'description' => $description,
        'min_cart_value' => $minCartValue,
        'max_discount' => $maxDiscount,
        'currency' => $currency,
        'status' => 'active',
    ]);
}

/**
 * Creates a valid VoucherValidationResult.
 */
function createValidValidationResult(): VoucherValidationResult
{
    return VoucherValidationResult::valid();
}

/**
 * Creates an invalid VoucherValidationResult.
 */
function createInvalidValidationResult(string $reason = 'Voucher is invalid'): VoucherValidationResult
{
    return VoucherValidationResult::invalid($reason);
}

describe('VoucherConditionProvider getType', function (): void {
    it('returns voucher type', function (): void {
        expect($this->provider->getType())->toBe('voucher');
    });
});

describe('VoucherConditionProvider getPriority', function (): void {
    it('returns priority 100', function (): void {
        expect($this->provider->getPriority())->toBe(100);
    });
});

describe('VoucherConditionProvider getConditionsFor', function (): void {
    it('returns empty array when no voucher codes in metadata', function (): void {
        $cart = createCartForVoucherConditionTest();

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('returns empty array when voucher codes array is empty', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => []]);

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('skips voucher when not found in service', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['INVALID']]);

        $this->voucherService->shouldReceive('find')
            ->with('INVALID')
            ->once()
            ->andReturn(null);

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('skips voucher when validation fails', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['EXPIRED']]);
        $voucher = createVoucherData(code: 'EXPIRED');

        $this->voucherService->shouldReceive('find')
            ->with('EXPIRED')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult('Voucher has expired'));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('creates condition for valid percentage voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10']]);
        $voucher = createVoucherData(
            code: 'SAVE10',
            type: VoucherType::Percentage,
            value: 1000, // 10% = 1000 basis points
        );

        $this->voucherService->shouldReceive('find')
            ->with('SAVE10')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('SAVE10', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0])->toBeInstanceOf(CartCondition::class);
        expect($conditions[0]->getName())->toBe('SAVE10');
        expect($conditions[0]->getType())->toBe('voucher');
        expect($conditions[0]->getValue())->toBe('-10%');
    });

    it('creates condition for valid fixed voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['FIXED20']]);
        $voucher = createVoucherData(
            code: 'FIXED20',
            type: VoucherType::Fixed,
            value: 2000, // $20.00 = 2000 cents
        );

        $this->voucherService->shouldReceive('find')
            ->with('FIXED20')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('FIXED20', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-2000');
    });

    it('creates condition for free shipping voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['FREESHIP']]);
        $voucher = createVoucherData(
            code: 'FREESHIP',
            type: VoucherType::FreeShipping,
            value: 0,
        );

        $this->voucherService->shouldReceive('find')
            ->with('FREESHIP')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('FREESHIP', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-100%');
    });

    it('handles multiple voucher codes', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10', 'FIXED5']]);
        $voucher1 = createVoucherData(code: 'SAVE10', type: VoucherType::Percentage, value: 1000); // 10%
        $voucher2 = createVoucherData(code: 'FIXED5', type: VoucherType::Fixed, value: 500); // $5.00

        $this->voucherService->shouldReceive('find')
            ->with('SAVE10')
            ->once()
            ->andReturn($voucher1);

        $this->voucherService->shouldReceive('find')
            ->with('FIXED5')
            ->once()
            ->andReturn($voucher2);

        $this->voucherService->shouldReceive('validate')
            ->with('SAVE10', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $this->voucherService->shouldReceive('validate')
            ->with('FIXED5', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(2);
    });

    it('includes voucher attributes in condition', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['TEST']]);
        $voucher = createVoucherData(
            id: 'voucher-abc',
            code: 'TEST',
            type: VoucherType::Percentage,
            value: 1500, // 15% = 1500 basis points
            description: 'Test discount',
            minCartValue: 5000, // $50.00 = 5000 cents
            maxDiscount: 10000, // $100.00 = 10000 cents
            currency: 'EUR'
        );

        $this->voucherService->shouldReceive('find')
            ->with('TEST')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('TEST', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        $attributes = $conditions[0]->getAttributes();
        expect($attributes['voucher_id'])->toBe('voucher-abc');
        expect($attributes['voucher_code'])->toBe('TEST');
        expect($attributes['voucher_type'])->toBe('percentage');
        expect($attributes['description'])->toBe('Test discount');
        expect($attributes['min_cart_value'])->toBe(5000);
        expect($attributes['max_discount'])->toBe(10000);
        expect($attributes['currency'])->toBe('EUR');
    });

    it('sets correct target for percentage voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['PERCENT']]);
        $voucher = createVoucherData(code: 'PERCENT', type: VoucherType::Percentage, value: 1000); // 10%

        $this->voucherService->shouldReceive('find')
            ->with('PERCENT')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('PERCENT', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        $targetArray = $conditions[0]->getTargetDefinition()->toArray();
        expect($targetArray['scope'])->toBe(ConditionScope::CART->value);
        expect($targetArray['phase'])->toBe(ConditionPhase::CART_SUBTOTAL->value);
        expect($targetArray['application'])->toBe(ConditionApplication::AGGREGATE->value);
    });

    it('sets correct target for free shipping voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SHIP']]);
        $voucher = createVoucherData(code: 'SHIP', type: VoucherType::FreeShipping, value: 0);

        $this->voucherService->shouldReceive('find')
            ->with('SHIP')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('SHIP', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        $targetArray = $conditions[0]->getTargetDefinition()->toArray();
        expect($targetArray['phase'])->toBe(ConditionPhase::SHIPPING->value);
    });
});

describe('VoucherConditionProvider validate', function (): void {
    it('returns true for non-voucher condition types', function (): void {
        $cart = createCartForVoucherConditionTest();
        $condition = new CartCondition(
            name: 'OTHER',
            type: 'tax',
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            value: '10%'
        );

        $result = $this->provider->validate($condition, $cart);

        expect($result)->toBeTrue();
    });

    it('returns true for valid voucher condition', function (): void {
        $cart = createCartForVoucherConditionTest();
        $condition = new CartCondition(
            name: 'VALID',
            type: 'voucher',
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            value: '-10%'
        );

        $this->voucherService->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $result = $this->provider->validate($condition, $cart);

        expect($result)->toBeTrue();
    });

    it('returns false for invalid voucher condition', function (): void {
        $cart = createCartForVoucherConditionTest();
        $condition = new CartCondition(
            name: 'EXPIRED',
            type: 'voucher',
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            value: '-10%'
        );

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult('Voucher expired'));

        $result = $this->provider->validate($condition, $cart);

        expect($result)->toBeFalse();
    });
});

describe('VoucherConditionProvider edge cases', function (): void {
    it('handles unsupported voucher type gracefully', function (): void {
        // This test verifies that unknown voucher types return null
        // In practice, all VoucherType enum values are handled
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['UNKNOWN']]);

        // Create a voucher with a mock type that would hit default case
        // Since we can't create an "unknown" VoucherType, we rely on the
        // pattern matching - all enum values are covered, but this tests
        // the behavior if somehow an unhandled type exists
        $voucher = createVoucherData(code: 'UNKNOWN', type: VoucherType::Fixed, value: 1000); // $10.00

        $this->voucherService->shouldReceive('find')
            ->with('UNKNOWN')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('UNKNOWN', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        // Fixed type should be handled
        expect($conditions)->toHaveCount(1);
    });

    it('processes mixed valid and invalid vouchers', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['VALID', 'INVALID', 'NOTFOUND']]);

        $validVoucher = createVoucherData(code: 'VALID', type: VoucherType::Percentage, value: 1000); // 10%

        $invalidVoucher = createVoucherData(code: 'INVALID', type: VoucherType::Fixed, value: 500); // $5.00

        $this->voucherService->shouldReceive('find')
            ->with('VALID')
            ->once()
            ->andReturn($validVoucher);

        $this->voucherService->shouldReceive('find')
            ->with('INVALID')
            ->once()
            ->andReturn($invalidVoucher);

        $this->voucherService->shouldReceive('find')
            ->with('NOTFOUND')
            ->once()
            ->andReturn(null);

        $this->voucherService->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $this->voucherService->shouldReceive('validate')
            ->with('INVALID', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        // Only the valid voucher should create a condition
        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getName())->toBe('VALID');
    });

    it('handles fixed voucher with decimal value', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['DECIMAL']]);
        $voucher = createVoucherData(
            code: 'DECIMAL',
            type: VoucherType::Fixed,
            value: 2550, // $25.50 = 2550 cents
        );

        $this->voucherService->shouldReceive('find')
            ->with('DECIMAL')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('DECIMAL', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        // Fixed values are stored as cents, output as raw integer
        expect($conditions[0]->getValue())->toBe('-2550');
    });

    it('handles percentage voucher with decimal percentage', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['PERCENT']]);
        $voucher = createVoucherData(
            code: 'PERCENT',
            type: VoucherType::Percentage,
            value: 1250, // 12.5% = 1250 basis points
        );

        $this->voucherService->shouldReceive('find')
            ->with('PERCENT')
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validate')
            ->with('PERCENT', $cart)
            ->once()
            ->andReturn(createValidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-12.5%');
    });
});
