<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Conditions\TargetPresets;

it('parses DSL targets with filters and grouping', function (): void {
    $dsl = 'items:attributes.category=electronics;quantity>=2@item_discount/per-item#seller';

    $target = ConditionTarget::from($dsl);

    expect($target->scope)->toBe(ConditionScope::ITEMS);
    expect($target->phase)->toBe(ConditionPhase::ITEM_DISCOUNT);
    expect($target->application)->toBe(ConditionApplication::PER_ITEM);
    expect($target->selector)->not->toBeNull();
    expect($target->selector?->filters())->toHaveCount(2);
    expect($target->selector?->grouping?->preset)->toBe('seller');
    expect($target->toDsl())->toBe($dsl);
});

it('rejects invalid non-DSL target strings', function (): void {
    expect(fn () => ConditionTarget::from('subtotal'))
        ->toThrow(InvalidArgumentException::class);
});

it('builds targets using the fluent builder', function (): void {
    $target = Target::cart()
        ->phase(ConditionPhase::SHIPPING)
        ->apply(ConditionApplication::PER_GROUP)
        ->where('destination.country', '=', 'US')
        ->groupingPreset('shipment')
        ->build();

    expect($target->scope)->toBe(ConditionScope::CART);
    expect($target->phase)->toBe(ConditionPhase::SHIPPING);
    expect($target->application)->toBe(ConditionApplication::PER_GROUP);
    expect($target->selector)->not->toBeNull();
    expect($target->selector?->grouping?->preset)->toBe('shipment');
    expect($target->selector?->filters())->toHaveCount(1);
});

it('serializes target definitions', function (): void {
    $target = ConditionTarget::from('cart@grand_total/aggregate');
    $array = $target->toArray();

    expect($array)->toHaveKeys(['scope', 'phase', 'application', 'selector', 'meta']);
    expect($array['scope'])->toBe('cart');
    expect($array['phase'])->toBe('grand_total');
    expect($array['application'])->toBe('aggregate');
});

it('provides helpful target presets', function (): void {
    expect(TargetPresets::cartSubtotal()->toDsl())->toBe('cart@cart_subtotal/aggregate')
        ->and(TargetPresets::cartGrandTotal()->toDsl())->toBe('cart@grand_total/aggregate')
        ->and(TargetPresets::itemsPerItem()->toDsl())->toBe('items@item_discount/per-item');
});
