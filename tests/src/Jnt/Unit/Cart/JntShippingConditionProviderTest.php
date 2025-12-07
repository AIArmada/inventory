<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Jnt\Cart\JntShippingCalculator;
use AIArmada\Jnt\Cart\JntShippingConditionProvider;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Support\Facades\DB;

function createConditionTestCart(string $identifier = 'condition-test'): Cart
{
    $storage = new DatabaseStorage(DB::connection('testing'), 'carts');

    return new Cart(
        storage: $storage,
        identifier: $identifier,
        events: null,
        instanceName: 'default'
    );
}

function createMockJntExpressService(): JntExpressService
{
    return new JntExpressService(
        customerCode: 'TEST123',
        password: 'password',
        config: [
            'environment' => 'testing',
            'base_urls' => ['testing' => 'https://demo.api.test'],
            'api_account' => '640826271705595946',
            'private_key' => '8e88c8477d4e4939859c560192fcafbc',
        ]
    );
}

function createRealCalculator(): JntShippingCalculator
{
    return new JntShippingCalculator(createMockJntExpressService());
}

function createTestCondition(string $type = 'shipping'): CartCondition
{
    return new CartCondition(
        name: 'test_condition',
        type: $type,
        target: [
            'scope' => 'cart',
            'phase' => 'shipping',
            'application' => 'aggregate',
        ],
        value: '800',
        attributes: ['affiliate_code' => 'TEST'],
    );
}

describe('JntShippingConditionProvider', function (): void {
    beforeEach(function (): void {
        config([
            'jnt.cart.quote_ttl_minutes' => 30,
            'jnt.shipping.origin' => [
                'name' => 'Test',
                'phone' => '0123456789',
                'address' => 'Test',
                'post_code' => '50000',
            ],
            'jnt.shipping.base_rate' => 800,
            'jnt.shipping.per_kg_rate' => 200,
            'jnt.shipping.min_charge' => 800,
            'jnt.shipping.default_estimated_days' => 3,
            'jnt.shipping.default_service_name' => 'J&T Express',
        ]);
    });

    it('returns empty conditions when no shipping address in cart metadata', function (): void {
        $cart = createConditionTestCart('no-address');

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('returns the correct type', function (): void {
        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());

        expect($provider->getType())->toBe('shipping');
    });

    it('returns the correct priority', function (): void {
        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());

        expect($provider->getPriority())->toBe(75);
    });

    it('validates condition returns true for other condition types', function (): void {
        $cart = createConditionTestCart('other-type');

        $condition = createTestCondition('discount');

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());

        expect($provider->validate($condition, $cart))->toBeTrue();
    });

    it('validates condition returns false when no shipping address', function (): void {
        $cart = createConditionTestCart('no-addr-validation');

        $condition = createTestCondition('shipping');

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());

        expect($provider->validate($condition, $cart))->toBeFalse();
    });

    it('validates condition returns true when shipping address exists', function (): void {
        $cart = createConditionTestCart('with-addr-validation');
        $cart->setMetadata('jnt_shipping_address', [
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
        ]);

        $condition = createTestCondition('shipping');

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());

        expect($provider->validate($condition, $cart))->toBeTrue();
    });

    it('creates condition from shipping quote when cart has items', function (): void {
        $cart = createConditionTestCart('quote-condition');
        $cart->setMetadata('jnt_shipping_address', [
            'name' => 'Test Receiver',
            'phone' => '0123456789',
            'address' => 'Test Address',
            'postCode' => '50000',
            'countryCode' => 'MYS',
        ]);

        // Add item with weight to get a valid quote
        $cart->add([
            'id' => 'weighted-item',
            'name' => 'Weighted Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1500],
        ]);

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0])->toBeInstanceOf(CartCondition::class);
        expect($conditions[0]->getType())->toBe('shipping');
    });

    it('returns empty when cart has no weight (zero weight)', function (): void {
        $cart = createConditionTestCart('no-weight');
        $cart->setMetadata('jnt_shipping_address', [
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
        ]);

        // No items, so zero weight

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('uses cached quote when valid', function (): void {
        $cart = createConditionTestCart('cached-quote');
        $cart->setMetadata('jnt_shipping_address', [
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
            'countryCode' => 'MYS',
        ]);
        $cart->setMetadata('jnt_shipping_quote', [
            'service_name' => 'J&T Express',
            'amount' => 999,
            'calculated_at' => now()->toISOString(),
            'cart_weight' => 0, // Match the cart's actual weight (no items)
        ]);

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('999');
    });

    it('recalculates when cart weight changes', function (): void {
        $cart = createConditionTestCart('weight-changed');
        $cart->setMetadata('jnt_shipping_address', [
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
            'countryCode' => 'MYS',
        ]);

        // Add item with weight
        $cart->add([
            'id' => 'new-item',
            'name' => 'New Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 3000],
        ]);

        // Old cached quote with different weight
        $cart->setMetadata('jnt_shipping_quote', [
            'service_name' => 'J&T Express',
            'amount' => 800,
            'calculated_at' => now()->toISOString(),
            'cart_weight' => 1000, // Different from current weight
        ]);

        $provider = new JntShippingConditionProvider(createMockJntExpressService(), createRealCalculator());
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        // Should have recalculated, not used cached 800
        expect($conditions[0]->getValue())->not->toBe('800');
    });
});
