<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionTargetBuilder;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Target;

describe('Target', function (): void {
    it('creates items target builder', function (): void {
        $builder = Target::items();

        expect($builder)->toBeInstanceOf(ConditionTargetBuilder::class);

        $target = $builder->build();
        expect($target->scope)->toBe(ConditionScope::ITEMS)
            ->and($target->phase)->toBe(ConditionPhase::ITEM_DISCOUNT)
            ->and($target->application)->toBe(ConditionApplication::PER_ITEM);
    });

    it('creates cart target builder', function (): void {
        $builder = Target::cart();

        expect($builder)->toBeInstanceOf(ConditionTargetBuilder::class);

        $target = $builder->build();
        expect($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::CART_SUBTOTAL)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates shipments target builder', function (): void {
        $builder = Target::shipments();

        expect($builder)->toBeInstanceOf(ConditionTargetBuilder::class);

        $target = $builder->build();
        expect($target->scope)->toBe(ConditionScope::SHIPMENTS)
            ->and($target->phase)->toBe(ConditionPhase::SHIPPING)
            ->and($target->application)->toBe(ConditionApplication::PER_GROUP);
    });

    it('creates payments target builder', function (): void {
        $builder = Target::payments();

        expect($builder)->toBeInstanceOf(ConditionTargetBuilder::class);

        $target = $builder->build();
        expect($target->scope)->toBe(ConditionScope::PAYMENTS)
            ->and($target->phase)->toBe(ConditionPhase::PAYMENT)
            ->and($target->application)->toBe(ConditionApplication::PER_PAYMENT);
    });

    it('creates fulfillments target builder', function (): void {
        $builder = Target::fulfillments();

        expect($builder)->toBeInstanceOf(ConditionTargetBuilder::class);

        $target = $builder->build();
        expect($target->scope)->toBe(ConditionScope::FULFILLMENTS)
            ->and($target->phase)->toBe(ConditionPhase::SHIPPING)
            ->and($target->application)->toBe(ConditionApplication::PER_GROUP);
    });

    it('creates custom target builder', function (): void {
        $builder = Target::custom();

        expect($builder)->toBeInstanceOf(ConditionTargetBuilder::class);

        $target = $builder->build();
        expect($target->scope)->toBe(ConditionScope::CUSTOM)
            ->and($target->phase)->toBe(ConditionPhase::CUSTOM)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('supports chaining for items target', function (): void {
        $target = Target::items()
            ->phase(ConditionPhase::ITEM_POST)
            ->applyPerUnit()
            ->build();

        expect($target->scope)->toBe(ConditionScope::ITEMS)
            ->and($target->phase)->toBe(ConditionPhase::ITEM_POST)
            ->and($target->application)->toBe(ConditionApplication::PER_UNIT);
    });

    it('supports chaining for cart target', function (): void {
        $target = Target::cart()
            ->phase(ConditionPhase::GRAND_TOTAL)
            ->build();

        expect($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::GRAND_TOTAL);
    });
});
