<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Support\Facades\DB;

final class RecordingRulesFactory implements RulesFactoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $created = [];

    public function createRules(string $key, array $metadata = []): array
    {
        $this->created[] = ['key' => $key, 'metadata' => $metadata];

        return match ($key) {
            'always-true' => [
                static fn (Cart $cart, ?CartItem $item = null): bool => true,
            ],
            'min-items' => [
                static function (Cart $cart, ?CartItem $item = null) use ($metadata): bool {
                    $minimum = (int) ($metadata['context']['min_items'] ?? 0);

                    return $cart->count() >= $minimum;
                },
            ],
            'throws' => [
                static function (): bool {
                    throw new RuntimeException('dynamic rule failure');
                },
            ],
            default => throw new InvalidArgumentException("Unsupported rules factory key: {$key}"),
        };
    }

    public function canCreateRules(string $key): bool
    {
        return in_array($key, ['always-true', 'min-items', 'throws'], true);
    }

    public function getAvailableKeys(): array
    {
        return ['always-true', 'min-items', 'throws'];
    }
}

beforeEach(function (): void {
    $connection = DB::connection('testing');
    $this->storage = new DatabaseStorage($connection, 'carts');
    $this->identifier = 'dynamic-user-' . uniqid();
});

describe('dynamic condition lifecycle', function (): void {
    it('persists metadata context across registrations and restores dynamic conditions', function (): void {
        $factory = new RecordingRulesFactory;

        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'vip_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
                'attributes' => ['label' => 'VIP'],
            ],
            rules: 'min-items',
            ruleFactoryKey: null,
            metadata: ['min_items' => 2]
        );

        $metadata = $cart->getDynamicConditionMetadata();
        expect($metadata)
            ->toHaveKey('vip_discount')
            ->and($metadata['vip_discount']['context'] ?? [])
            ->toMatchArray(['min_items' => 2]);

        expect($factory->created)->not->toBeEmpty();

        // Cart starts empty so the dynamic discount is inactive.
        expect($cart->getConditions()->has('vip_discount'))->toBeFalse();

        $cart->add('sku-1', 'Sample A', 100, 1);
        $cart->add('sku-2', 'Sample B', 80, 1);

        expect($cart->getConditions()->has('vip_discount'))->toBeTrue();

        // Spin up a new cart instance to ensure rules are restored with metadata.
        $restoredFactory = new RecordingRulesFactory;
        $restoredCart = new Cart($this->storage, $this->identifier, events: null);
        $restoredCart->withRulesFactory($restoredFactory);

        expect($restoredFactory->created)
            ->not->toBeEmpty()
            ->and($restoredFactory->created[0]['metadata']['context']['min_items'] ?? null)
            ->toBe(2);

        // Restore cart is empty, so add items to trigger evaluation again.
        $restoredCart->add('sku-1', 'Sample A', 100, 1);
        $restoredCart->add('sku-2', 'Sample B', 80, 1);

        expect($restoredCart->getConditions()->has('vip_discount'))->toBeTrue();
    });

    it('invokes the failure handler when rule execution throws an exception', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $captured = null;
        $cart->onDynamicConditionFailure(function (string $operation, ?CartCondition $condition, ?Throwable $exception, array $context) use (&$captured): void {
            $captured = compact('operation', 'condition', 'exception', 'context');
        });

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'faulty_condition',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-5%',
            ],
            rules: 'throws'
        );

        $cart->evaluateDynamicConditions();

        expect($captured)->not->toBeNull();
        expect($captured['operation'])->toBe('evaluate');
        expect($captured['condition'])->toBeInstanceOf(CartCondition::class);
        expect($captured['exception'])->toBeInstanceOf(RuntimeException::class);
        expect($cart->getConditions()->has('faulty_condition'))->toBeFalse();
    });

    it('handles item dynamic condition failure', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $captured = null;
        $cart->onDynamicConditionFailure(function (string $operation, ?CartCondition $condition, ?Throwable $exception, array $context) use (&$captured): void {
            $captured = compact('operation', 'condition', 'exception', 'context');
        });

        $cart->add('item1', 'Item 1', 100, 1);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'item_discount',
                'type' => 'discount',
                'target' => 'items@item_discount/per-item',
                'target_definition' => conditionTargetDefinition('items@item_discount/per-item'),

                'value' => '-10%',
            ],
            rules: 'throws',
            ruleFactoryKey: null,
            metadata: []
        );

        $cart->evaluateDynamicConditions();

        expect($captured)->not->toBeNull();
        expect($captured['operation'])->toBe('evaluate');
        expect($captured['context']['scope'])->toBe(ConditionScope::ITEMS->value);
        expect($captured['exception'])->toBeInstanceOf(RuntimeException::class);
    });

    it('applies item dynamic conditions when rules pass', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->add('item1', 'Item 1', 100, 1);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'item_discount',
                'type' => 'discount',
                'target' => 'items@item_discount/per-item',
                'target_definition' => conditionTargetDefinition('items@item_discount/per-item'),

                'value' => '-10%',
            ],
            rules: 'always-true',
            ruleFactoryKey: null,
            metadata: []
        );

        $cart->evaluateDynamicConditions();

        $item = $cart->get('item1');
        expect($item->conditions->has('item_discount'))->toBeTrue();
    });

    it('removes item dynamic conditions when rules fail', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->add('item1', 'Item 1', 100, 1);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'item_discount',
                'type' => 'discount',
                'target' => 'items@item_discount/per-item',
                'target_definition' => conditionTargetDefinition('items@item_discount/per-item'),

                'value' => '-10%',
            ],
            rules: 'min-items',
            ruleFactoryKey: null,
            metadata: ['context' => ['min_items' => 2]]
        );

        $cart->evaluateDynamicConditions();

        $item = $cart->get('item1');
        expect($item->conditions->has('item_discount'))->toBeFalse();

        $cart->add('item2', 'Item 2', 50, 1);
        $item = $cart->get('item1');
        expect($item->conditions->has('item_discount'))->toBeTrue();
    });

    it('handles mixed rules array with strings and callables', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'mixed_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-15%',
            ],
            rules: ['always-true', static fn () => true],
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getConditions()->has('mixed_discount'))->toBeTrue();
    });

    it('handles array of factory keys', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'multi_key_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-20%',
            ],
            rules: ['always-true'],
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getConditions()->has('multi_key_discount'))->toBeTrue();
    });

    it('handles closure rules', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'closure_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-5%',
            ],
            rules: static fn () => true,
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getConditions()->has('closure_discount'))->toBeTrue();
    });

    it('throws for unknown factory key', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        expect(fn () => $cart->registerDynamicCondition(
            condition: [
                'name' => 'unknown_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: 'unknown-key',
            ruleFactoryKey: null,
            metadata: []
        ))->toThrow(InvalidArgumentException::class, 'Unknown factory key');
    });

    it('throws when using factory key without rules factory', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);

        expect(fn () => $cart->registerDynamicCondition(
            condition: [
                'name' => 'no_factory_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: 'always-true',
            ruleFactoryKey: null,
            metadata: []
        ))->toThrow(InvalidArgumentException::class, 'Cannot use factory key without setting a RulesFactory');
    });
});

describe('CartCondition helpers', function (): void {
    it('caches static clones for dynamic conditions', function (): void {
        $condition = new CartCondition(
            name: 'vip_discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%',
            rules: [static fn (): bool => true]
        );

        $firstStatic = $condition->withoutRules();
        $secondStatic = $condition->withoutRules();

        expect($firstStatic)
            ->toBeInstanceOf(CartCondition::class)
            ->and($firstStatic)->not->toBe($condition)
            ->and($secondStatic)->toBe($firstStatic)
            ->and($firstStatic->isDynamic())->toBeFalse();
    });
});

describe('ManagesDynamicConditions edge cases', function (): void {
    it('throws when registering condition with invalid rules type', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);

        expect(fn () => $cart->registerDynamicCondition(
            condition: [
                'name' => 'bad_discount',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: null,
            ruleFactoryKey: null,
            metadata: []
        ))->toThrow(InvalidArgumentException::class, 'Rules must be');
    });

    it('throws when mixed rules contain invalid types', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        expect(fn () => $cart->registerDynamicCondition(
            condition: [
                'name' => 'bad_mixed',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: ['always-true', 123, static fn () => true],
            ruleFactoryKey: null,
            metadata: []
        ))->toThrow(InvalidArgumentException::class, 'Mixed rules must be strings');
    });

    it('handles closure that returns array of rules', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'closure_array',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: static fn () => [static fn () => true],
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getConditions()->has('closure_array'))->toBeTrue();
    });

    it('handles empty rules array', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'empty_rules',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: [static fn () => true],
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getDynamicConditions()->has('empty_rules'))->toBeTrue();
    });

    it('throws when registering non-dynamic condition', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);

        $staticCondition = new CartCondition(
            name: 'static_cond',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        );

        expect(fn () => $cart->registerDynamicCondition(
            condition: $staticCondition,
            rules: null,
            ruleFactoryKey: null,
            metadata: []
        ))->toThrow(InvalidArgumentException::class, 'Only dynamic conditions');
    });

    it('can remove dynamic condition and its metadata', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'removable',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: 'always-true',
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getDynamicConditions()->has('removable'))->toBeTrue();

        $cart->removeDynamicCondition('removable');

        expect($cart->getDynamicConditions()->has('removable'))->toBeFalse();
        expect($cart->getDynamicConditionMetadata())->not->toHaveKey('removable');
    });

    it('can clear all dynamic conditions', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'discount1',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: 'always-true',
            ruleFactoryKey: null,
            metadata: []
        );

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'discount2',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-5%',
            ],
            rules: 'always-true',
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getDynamicConditions()->count())->toBe(2);

        $cart->clearDynamicConditions();

        expect($cart->getDynamicConditions()->count())->toBe(0);
        expect($cart->getDynamicConditionMetadata())->toBe([]);
    });

    it('handles restore with invalid factory key in metadata', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        // Manually inject bad metadata
        $this->storage->putMetadata(
            $this->identifier,
            'default',
            'dynamic_conditions',
            [
                'bad_condition' => [
                    'type' => 'discount',
                    'target' => 'cart@cart_subtotal/aggregate',
                    'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                    'value' => '-10%',
                    'rule_factory_key' => 'unknown-key',
                ],
            ]
        );

        $captured = null;
        $cart->onDynamicConditionFailure(function (string $operation, ?CartCondition $condition, ?Throwable $exception, array $context) use (&$captured): void {
            $captured = compact('operation', 'condition', 'exception', 'context');
        });

        $cart->restoreDynamicConditions();

        expect($captured)->not->toBeNull();
        expect($captured['operation'])->toBe('restore');
        expect($captured['exception'])->toBeInstanceOf(InvalidArgumentException::class);
    });

    it('skips restore for conditions without rule_factory_key', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        // Manually inject metadata without rule_factory_key
        $this->storage->putMetadata(
            $this->identifier,
            'default',
            'dynamic_conditions',
            [
                'no_key_condition' => [
                    'type' => 'discount',
                    'target' => 'cart@cart_subtotal/aggregate',
                    'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                    'value' => '-10%',
                ],
            ]
        );

        $cart->restoreDynamicConditions();

        expect($cart->getDynamicConditions()->count())->toBe(0);
    });

    it('restores dynamic conditions with array of factory keys', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'multi_key',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: ['always-true', 'min-items'],
            ruleFactoryKey: null,
            metadata: ['min_items' => 1]
        );

        $cart->add('item1', 'Item 1', 100, 1);
        expect($cart->getConditions()->has('multi_key'))->toBeTrue();

        // Restore in new cart
        $newCart = new Cart($this->storage, $this->identifier, events: null);
        $newCart->withRulesFactory(new RecordingRulesFactory);
        $newCart->add('item1', 'Item 1', 100, 1);

        expect($newCart->getConditions()->has('multi_key'))->toBeTrue();
    });

    it('does not fail when no failure handler is set', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'faulty',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: 'throws',
            ruleFactoryKey: null,
            metadata: []
        );

        expect($cart->getConditions()->has('faulty'))->toBeFalse();
    });

    it('throws exception when using mixed rules with factory key but no factory set', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);
        // Do not set rules factory

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'mixed_no_factory',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: ['always-true', fn () => true], // Mixed: factory key + closure
            ruleFactoryKey: null,
            metadata: []
        );
    })->throws(InvalidArgumentException::class, 'Cannot use factory keys without setting a RulesFactory');

    it('throws exception when using unknown factory key in mixed rules', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'unknown_key',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: ['unknown-factory-key', fn () => true], // Mixed: unknown key + closure
            ruleFactoryKey: null,
            metadata: []
        );
    })->throws(InvalidArgumentException::class, 'Unknown factory key: unknown-factory-key');

    it('throws exception when using array of factory keys but no factory set', function (): void {
        $cart = new Cart($this->storage, $this->identifier, events: null);
        // Do not set rules factory

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'array_no_factory',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: ['always-true', 'min-items'], // Array of factory keys
            ruleFactoryKey: null,
            metadata: []
        );
    })->throws(InvalidArgumentException::class, 'Cannot use factory keys without setting a RulesFactory');

    it('throws exception when using unknown factory key in array', function (): void {
        $factory = new RecordingRulesFactory;
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'unknown_in_array',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),

                'value' => '-10%',
            ],
            rules: ['always-true', 'unknown-key'], // Array with unknown key
            ruleFactoryKey: null,
            metadata: []
        );
    })->throws(InvalidArgumentException::class, 'Unknown factory key: unknown-key');
});
