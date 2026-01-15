<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    $storage = new InMemoryStorage;
    $this->cart = new Cart($storage, 'test-user', null, 'default');
});

describe('ManagesConditions Trait', function (): void {
    it('removes conditions by type successfully', function (): void {
        // Add multiple conditions of different types
        $this->cart->addCondition(new CartCondition(
            name: 'tax1',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '+10%'
        ));

        $this->cart->addCondition(new CartCondition(
            name: 'tax2',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '+5%'
        ));

        $this->cart->addCondition(new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-20%'
        ));

        expect($this->cart->getConditions())->toHaveCount(3);

        // Remove all tax conditions
        $result = $this->cart->removeConditionsByType('tax');

        expect($result)->toBeTrue();
        expect($this->cart->getConditions())->toHaveCount(1);
        expect($this->cart->getCondition('discount'))->not->toBeNull();
        expect($this->cart->getCondition('tax1'))->toBeNull();
        expect($this->cart->getCondition('tax2'))->toBeNull();
    });

    it('returns false when removing conditions by non-existent type', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'tax',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '+10%'
        ));

        $result = $this->cart->removeConditionsByType('shipping');

        expect($result)->toBeFalse();
        expect($this->cart->getConditions())->toHaveCount(1);
    });

    it('returns false when removing item condition that does not exist', function (): void {
        $this->cart->add(
            id: 'prod-1',
            name: 'Product',
            price: 100,
            quantity: 1
        );

        $result = $this->cart->removeItemCondition('prod-1', 'nonexistent');

        expect($result)->toBeFalse();
    });

    it('returns false when removing condition from non-existent item', function (): void {
        $result = $this->cart->removeItemCondition('nonexistent-item', 'some-condition');

        expect($result)->toBeFalse();
    });

    it('gets shipping condition', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'standard_shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '+15',
            attributes: ['method' => 'standard']
        ));

        $shipping = $this->cart->getShipping();

        expect($shipping)->not->toBeNull();
        expect($shipping->getName())->toBe('standard_shipping');
        expect($shipping->getType())->toBe('shipping');
    });

    it('returns null when no shipping condition exists', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'tax',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '+10%'
        ));

        $shipping = $this->cart->getShipping();

        expect($shipping)->toBeNull();
    });

    it('gets shipping method from condition attributes', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'express_shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '+25',
            attributes: ['method' => 'express']
        ));

        $method = $this->cart->getShippingMethod();

        expect($method)->toBe('express');
    });

    it('returns null when shipping condition has no method attribute', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '+15'
        ));

        $method = $this->cart->getShippingMethod();

        expect($method)->toBeNull();
    });

    it('returns null for shipping method when no shipping condition exists', function (): void {
        $method = $this->cart->getShippingMethod();

        expect($method)->toBeNull();
    });

    it('gets shipping value as float from positive operator', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '+15.50'
        ));

        $value = $this->cart->getShippingValue();

        expect($value)->toBe(15.50);
    });

    it('gets shipping value as negative float from minus operator', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10.00'
        ));

        $value = $this->cart->getShippingValue();

        expect($value)->toBe(-10.00);
    });

    it('gets shipping value from numeric string without operator', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '25.99'
        ));

        $value = $this->cart->getShippingValue();

        expect($value)->toBe(25.99);
    });

    it('gets shipping value from integer', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: 2000
        ));

        $value = $this->cart->getShippingValue();

        expect($value)->toBe(2000.0);  // Returns as float
    });

    it('returns null for shipping value when no shipping condition exists', function (): void {
        $value = $this->cart->getShippingValue();

        expect($value)->toBeNull();
    });

    it('handles multiplication operator in shipping value', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '*1.5'
        ));

        $value = $this->cart->getShippingValue();

        // Multiplication operator returns positive value
        expect($value)->toBe(1.5);
    });

    it('handles percentage operator in shipping value', function (): void {
        $this->cart->addCondition(new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '%10'
        ));

        $value = $this->cart->getShippingValue();

        expect($value)->toBe(10.0);
    });

    it('handles Traversable condition input', function (): void {
        $conditions = new ArrayIterator([
            new CartCondition(
                name: 'tax1',
                type: 'tax',
                target: 'cart@cart_subtotal/aggregate',
                value: '+10%'
            ),
            new CartCondition(
                name: 'discount',
                type: 'discount',
                target: 'cart@cart_subtotal/aggregate',
                value: '-5%'
            ),
        ]);

        $this->cart->addCondition($conditions);

        expect($this->cart->getConditions())->toHaveCount(2);
        expect($this->cart->getCondition('tax1'))->not->toBeNull();
        expect($this->cart->getCondition('discount'))->not->toBeNull();
    });

    it('handles associative array condition input', function (): void {
        $conditionArray = [
            'name' => 'tax_from_array',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'target_definition' => ConditionTarget::from('cart@cart_subtotal/aggregate')->toArray(),
            'value' => '+8%',
        ];

        $this->cart->addCondition($conditionArray);

        $condition = $this->cart->getCondition('tax_from_array');
        expect($condition)->not->toBeNull();
        expect($condition->getType())->toBe('tax');
        expect($condition->getValue())->toBe('+8%');
    });
});
