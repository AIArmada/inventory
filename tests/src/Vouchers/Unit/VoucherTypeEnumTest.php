<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherType;

describe('VoucherType Enum', function (): void {
    describe('basic types', function (): void {
        it('has percentage type', function (): void {
            expect(VoucherType::Percentage->value)->toBe('percentage');
            expect(VoucherType::Percentage->label())->toBe('Percentage Discount');
        });

        it('has fixed type', function (): void {
            expect(VoucherType::Fixed->value)->toBe('fixed');
            expect(VoucherType::Fixed->label())->toBe('Fixed Amount Discount');
        });

        it('has free shipping type', function (): void {
            expect(VoucherType::FreeShipping->value)->toBe('free_shipping');
            expect(VoucherType::FreeShipping->label())->toBe('Free Shipping');
        });
    });

    describe('compound types', function (): void {
        it('has buy x get y type', function (): void {
            expect(VoucherType::BuyXGetY->value)->toBe('buy_x_get_y');
            expect(VoucherType::BuyXGetY->label())->toBe('Buy X Get Y');
        });

        it('has tiered type', function (): void {
            expect(VoucherType::Tiered->value)->toBe('tiered');
            expect(VoucherType::Tiered->label())->toBe('Tiered Discount');
        });

        it('has bundle type', function (): void {
            expect(VoucherType::Bundle->value)->toBe('bundle');
            expect(VoucherType::Bundle->label())->toBe('Bundle Discount');
        });

        it('has cashback type', function (): void {
            expect(VoucherType::Cashback->value)->toBe('cashback');
            expect(VoucherType::Cashback->label())->toBe('Cashback');
        });
    });

    describe('isCompound method', function (): void {
        it('returns false for simple types', function (): void {
            expect(VoucherType::Percentage->isCompound())->toBeFalse();
            expect(VoucherType::Fixed->isCompound())->toBeFalse();
            expect(VoucherType::FreeShipping->isCompound())->toBeFalse();
        });

        it('returns true for compound types', function (): void {
            expect(VoucherType::BuyXGetY->isCompound())->toBeTrue();
            expect(VoucherType::Tiered->isCompound())->toBeTrue();
            expect(VoucherType::Bundle->isCompound())->toBeTrue();
            expect(VoucherType::Cashback->isCompound())->toBeTrue();
        });
    });

    describe('appliesAtCheckout method', function (): void {
        it('returns true for simple types', function (): void {
            expect(VoucherType::Percentage->appliesAtCheckout())->toBeTrue();
            expect(VoucherType::Fixed->appliesAtCheckout())->toBeTrue();
            expect(VoucherType::FreeShipping->appliesAtCheckout())->toBeTrue();
        });

        it('returns true for most compound types', function (): void {
            expect(VoucherType::BuyXGetY->appliesAtCheckout())->toBeTrue();
            expect(VoucherType::Tiered->appliesAtCheckout())->toBeTrue();
            expect(VoucherType::Bundle->appliesAtCheckout())->toBeTrue();
        });

        it('returns false for cashback', function (): void {
            expect(VoucherType::Cashback->appliesAtCheckout())->toBeFalse();
        });
    });

    describe('requiresPostCheckout method', function (): void {
        it('returns false for simple types', function (): void {
            expect(VoucherType::Percentage->requiresPostCheckout())->toBeFalse();
            expect(VoucherType::Fixed->requiresPostCheckout())->toBeFalse();
            expect(VoucherType::FreeShipping->requiresPostCheckout())->toBeFalse();
        });

        it('returns false for most compound types', function (): void {
            expect(VoucherType::BuyXGetY->requiresPostCheckout())->toBeFalse();
            expect(VoucherType::Tiered->requiresPostCheckout())->toBeFalse();
            expect(VoucherType::Bundle->requiresPostCheckout())->toBeFalse();
        });

        it('returns true for cashback', function (): void {
            expect(VoucherType::Cashback->requiresPostCheckout())->toBeTrue();
        });
    });

    describe('hasItemLevelDiscounts method', function (): void {
        it('returns false for simple types', function (): void {
            expect(VoucherType::Percentage->hasItemLevelDiscounts())->toBeFalse();
            expect(VoucherType::Fixed->hasItemLevelDiscounts())->toBeFalse();
            expect(VoucherType::FreeShipping->hasItemLevelDiscounts())->toBeFalse();
        });

        it('returns true for buy x get y', function (): void {
            expect(VoucherType::BuyXGetY->hasItemLevelDiscounts())->toBeTrue();
        });

        it('returns true for bundle', function (): void {
            expect(VoucherType::Bundle->hasItemLevelDiscounts())->toBeTrue();
        });

        it('returns false for tiered', function (): void {
            expect(VoucherType::Tiered->hasItemLevelDiscounts())->toBeFalse();
        });

        it('returns false for cashback', function (): void {
            expect(VoucherType::Cashback->hasItemLevelDiscounts())->toBeFalse();
        });
    });

    describe('simpleTypes method', function (): void {
        it('returns only simple types', function (): void {
            $simpleTypes = VoucherType::simpleTypes();

            expect($simpleTypes)->toHaveCount(3);
            expect($simpleTypes)->toContain(VoucherType::Percentage);
            expect($simpleTypes)->toContain(VoucherType::Fixed);
            expect($simpleTypes)->toContain(VoucherType::FreeShipping);
            expect($simpleTypes)->not->toContain(VoucherType::BuyXGetY);
            expect($simpleTypes)->not->toContain(VoucherType::Tiered);
            expect($simpleTypes)->not->toContain(VoucherType::Bundle);
            expect($simpleTypes)->not->toContain(VoucherType::Cashback);
        });
    });

    describe('compoundTypes method', function (): void {
        it('returns only compound types', function (): void {
            $compoundTypes = VoucherType::compoundTypes();

            expect($compoundTypes)->toHaveCount(4);
            expect($compoundTypes)->toContain(VoucherType::BuyXGetY);
            expect($compoundTypes)->toContain(VoucherType::Tiered);
            expect($compoundTypes)->toContain(VoucherType::Bundle);
            expect($compoundTypes)->toContain(VoucherType::Cashback);
            expect($compoundTypes)->not->toContain(VoucherType::Percentage);
            expect($compoundTypes)->not->toContain(VoucherType::Fixed);
            expect($compoundTypes)->not->toContain(VoucherType::FreeShipping);
        });
    });

    describe('color method', function (): void {
        it('returns primary for percentage', function (): void {
            expect(VoucherType::Percentage->color())->toBe('primary');
        });

        it('returns success for fixed', function (): void {
            expect(VoucherType::Fixed->color())->toBe('success');
        });

        it('returns info for free shipping', function (): void {
            expect(VoucherType::FreeShipping->color())->toBe('info');
        });

        it('returns warning for buy x get y', function (): void {
            expect(VoucherType::BuyXGetY->color())->toBe('warning');
        });

        it('returns violet for tiered', function (): void {
            expect(VoucherType::Tiered->color())->toBe('violet');
        });

        it('returns rose for bundle', function (): void {
            expect(VoucherType::Bundle->color())->toBe('rose');
        });

        it('returns amber for cashback', function (): void {
            expect(VoucherType::Cashback->color())->toBe('amber');
        });
    });
});
