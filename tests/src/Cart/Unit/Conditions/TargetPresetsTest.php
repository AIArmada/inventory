<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\TargetPresets;

describe('TargetPresets', function (): void {
    it('creates cart subtotal target', function (): void {
        $target = TargetPresets::cartSubtotal();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::CART_SUBTOTAL)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart grand total target', function (): void {
        $target = TargetPresets::cartGrandTotal();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::GRAND_TOTAL)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart shipping target', function (): void {
        $target = TargetPresets::cartShipping();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::SHIPPING)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart taxable target', function (): void {
        $target = TargetPresets::cartTaxable();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::TAXABLE)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart tax target', function (): void {
        $target = TargetPresets::cartTax();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::TAX)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates items per item target', function (): void {
        $target = TargetPresets::itemsPerItem();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::ITEMS)
            ->and($target->phase)->toBe(ConditionPhase::ITEM_DISCOUNT)
            ->and($target->application)->toBe(ConditionApplication::PER_ITEM);
    });

    it('creates items pre item target', function (): void {
        $target = TargetPresets::itemsPreItem();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::ITEMS)
            ->and($target->phase)->toBe(ConditionPhase::PRE_ITEM)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates custom aggregate target', function (): void {
        $target = TargetPresets::customAggregate();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CUSTOM)
            ->and($target->phase)->toBe(ConditionPhase::CUSTOM)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });
});
