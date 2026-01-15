<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Testing\ExampleRulesFactory;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
    $this->cart = new Cart($this->storage, 'test-identifier');
    $this->rulesFactory = new ExampleRulesFactory;
});

it('can set and get rules factory', function (): void {
    expect($this->cart->getRulesFactory())->toBeNull();

    $this->cart->setRulesFactory($this->rulesFactory);

    expect($this->cart->getRulesFactory())->toBe($this->rulesFactory);
});

it('can register dynamic condition with persistence', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    $condition = new CartCondition(
        name: 'test_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%',
        rules: [fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >= 100]
    );

    $this->cart->registerDynamicCondition($condition, null, 'min_order_discount');

    expect($this->cart->getDynamicConditions())->toHaveCount(1);
    expect($this->cart->getDynamicConditions()->has('test_discount'))->toBeTrue();

    // Check metadata was persisted
    $metadata = $this->cart->getDynamicConditionMetadata();
    expect($metadata)->toHaveKey('test_discount');
    expect($metadata['test_discount']['rule_factory_key'])->toBe('min_order_discount');
    expect($metadata['test_discount']['type'])->toBe('discount');
    expect($metadata['test_discount']['target_definition']['phase'])->toBe('cart_subtotal');
    expect($metadata['test_discount']['value'])->toBe('-10%');
});

it('can register dynamic condition without persistence', function (): void {
    $condition = new CartCondition(
        name: 'temp_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-5%',
        rules: [fn ($cart) => true]
    );

    $this->cart->registerDynamicCondition($condition);

    expect($this->cart->getDynamicConditions())->toHaveCount(1);

    // No metadata should be persisted
    $metadata = $this->cart->getDynamicConditionMetadata();
    expect($metadata)->toBeEmpty();
});

it('can restore dynamic conditions from metadata', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    // First register and persist a condition
    $condition = new CartCondition(
        name: 'persistent_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-15%',
        rules: [fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >= 100]
    );

    $this->cart->registerDynamicCondition($condition, null, 'min_order_discount');

    // Create a new cart instance (simulating new request)
    $newCart = new Cart($this->storage, 'test-identifier');
    $newCart->setRulesFactory($this->rulesFactory);

    expect($newCart->getDynamicConditions())->toHaveCount(0);

    // Restore conditions
    $newCart->restoreDynamicConditions();

    expect($newCart->getDynamicConditions())->toHaveCount(1);
    expect($newCart->getDynamicConditions()->has('persistent_discount'))->toBeTrue();

    $restoredCondition = $newCart->getDynamicConditions()->get('persistent_discount');
    expect($restoredCondition->getType())->toBe('discount');
    expect($restoredCondition->getTargetDefinition()->toDsl())->toBe('cart@cart_subtotal/aggregate');
    expect($restoredCondition->getValue())->toBe('-15%');
    expect($restoredCondition->isDynamic())->toBeTrue();
});

it('withRulesFactory automatically restores conditions', function (): void {
    // First cart: register persistent condition
    $cart1 = new Cart($this->storage, 'test-identifier');
    $cart1->setRulesFactory($this->rulesFactory);

    $condition = new CartCondition(
        name: 'auto_restore_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-20%',
        rules: [fn ($cart) => true]
    );

    $cart1->registerDynamicCondition($condition, null, 'min_order_discount');

    // Second cart: should automatically restore
    $cart2 = new Cart($this->storage, 'test-identifier');
    $cart2->withRulesFactory($this->rulesFactory);

    expect($cart2->getDynamicConditions())->toHaveCount(1);
    expect($cart2->getDynamicConditions()->has('auto_restore_discount'))->toBeTrue();
});

it('can remove dynamic condition and its metadata', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    $condition = new CartCondition(
        name: 'removable_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%',
        rules: [fn ($cart) => true]
    );

    $this->cart->registerDynamicCondition($condition, null, 'min_order_discount');

    expect($this->cart->getDynamicConditions())->toHaveCount(1);
    expect($this->cart->getDynamicConditionMetadata())->toHaveKey('removable_discount');

    $this->cart->removeDynamicCondition('removable_discount');

    expect($this->cart->getDynamicConditions())->toHaveCount(0);
    expect($this->cart->getDynamicConditionMetadata())->not->toHaveKey('removable_discount');
});

it('can clear all dynamic conditions and metadata', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    // Register multiple conditions
    for ($i = 1; $i <= 3; $i++) {
        $condition = new CartCondition(
            name: "discount_{$i}",
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: "-{$i}0%",
            rules: [fn ($cart) => true]
        );

        $this->cart->registerDynamicCondition($condition, null, 'min_order_discount');
    }

    expect($this->cart->getDynamicConditions())->toHaveCount(3);
    expect($this->cart->getDynamicConditionMetadata())->toHaveCount(3);

    $this->cart->clearDynamicConditions();

    expect($this->cart->getDynamicConditions())->toHaveCount(0);
    expect($this->cart->getDynamicConditionMetadata())->toHaveCount(0);
});

it('skips conditions without rule factory during restoration', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    // Manually insert metadata with invalid rule factory key
    $this->storage->putMetadata(
        'test-identifier',
        'default',
        'dynamic_conditions',
        [
            'invalid_condition' => [
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
                'target_definition' => ConditionTarget::from('cart@cart_subtotal/aggregate')->toArray(),
                'value' => '-10%',
                'rule_factory_key' => 'non_existent_key',
                'created_at' => time(),
            ],
        ]
    );

    $newCart = new Cart($this->storage, 'test-identifier');
    $newCart->withRulesFactory($this->rulesFactory);

    // Should not restore invalid condition
    expect($newCart->getDynamicConditions())->toHaveCount(0);
});

it('skips restoration when no rules factory is set', function (): void {
    // Manually insert metadata
    $this->storage->putMetadata(
        'test-identifier',
        'default',
        'dynamic_conditions',
        [
            'some_condition' => [
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
                'target_definition' => ConditionTarget::from('cart@cart_subtotal/aggregate')->toArray(),
                'value' => '-10%',
                'rule_factory_key' => 'min_order_discount',
                'created_at' => time(),
            ],
        ]
    );

    $cart = new Cart($this->storage, 'test-identifier');
    $cart->restoreDynamicConditions();

    // Should not restore without rules factory
    expect($cart->getDynamicConditions())->toHaveCount(0);
});

it('handles metadata with missing rule factory key gracefully', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    // Manually insert metadata without rule factory key
    $this->storage->putMetadata(
        'test-identifier',
        'default',
        'dynamic_conditions',
        [
            'no_factory_key' => [
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
                'target_definition' => ConditionTarget::from('cart@cart_subtotal/aggregate')->toArray(),
                'value' => '-10%',
                'created_at' => time(),
                // Missing 'rule_factory_key'
            ],
        ]
    );

    $newCart = new Cart($this->storage, 'test-identifier');
    $newCart->withRulesFactory($this->rulesFactory);

    // Should not restore condition without factory key
    expect($newCart->getDynamicConditions())->toHaveCount(0);
});

it('restores conditions with custom metadata values', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    // Register condition with custom attributes and order
    $condition = new CartCondition(
        name: 'custom_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-25%',
        attributes: ['description' => 'Special holiday discount', 'category' => 'seasonal'],
        order: 100,
        rules: [fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >= 50]
    );

    $this->cart->registerDynamicCondition($condition, null, 'min_order_discount');

    // Restore in new cart
    $newCart = new Cart($this->storage, 'test-identifier');
    $newCart->withRulesFactory($this->rulesFactory);

    $restoredCondition = $newCart->getDynamicConditions()->get('custom_discount');

    expect($restoredCondition->getAttributes())->toBe([
        'description' => 'Special holiday discount',
        'category' => 'seasonal',
    ]);
    expect($restoredCondition->getOrder())->toBe(100);
    expect($restoredCondition->getValue())->toBe('-25%');
});

it('throws exception when registering non-dynamic condition with factory key', function (): void {
    $this->cart->setRulesFactory($this->rulesFactory);

    $staticCondition = new CartCondition(
        name: 'static_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
        // No rules = not dynamic
    );

    expect(fn () => $this->cart->registerDynamicCondition($staticCondition, null, 'min_order_discount'))
        ->toThrow(InvalidArgumentException::class, 'Only dynamic conditions (with rules) can be registered.');
});
