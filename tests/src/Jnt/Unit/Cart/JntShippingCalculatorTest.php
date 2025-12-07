<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Jnt\Cart\JntShippingCalculator;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Support\Facades\DB;

function createTestCart(string $identifier = 'test-user'): Cart
{
    $storage = new DatabaseStorage(DB::connection('testing'), 'carts');

    return new Cart(
        storage: $storage,
        identifier: $identifier,
        events: null,
        instanceName: 'default'
    );
}

function createTestJntExpressService(): JntExpressService
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

describe('JntShippingCalculator', function (): void {
    beforeEach(function (): void {
        config([
            'jnt.shipping.base_rate' => 800,
            'jnt.shipping.per_kg_rate' => 200,
            'jnt.shipping.min_charge' => 800,
            'jnt.shipping.default_estimated_days' => 3,
            'jnt.shipping.default_service_name' => 'J&T Express',
            'jnt.shipping.default_service_type' => 'EZ',
            'jnt.shipping.origin' => [
                'name' => 'Test Sender',
                'phone' => '0123456789',
                'address' => '123 Test Street',
                'post_code' => '50000',
                'country_code' => 'MYS',
                'state' => 'Kuala Lumpur',
            ],
            'jnt.shipping.region_multipliers' => [
                'sabah' => 1.5,
                'sarawak' => 1.5,
            ],
        ]);
    });

    it('calculates cart weight from items correctly', function (): void {
        $cart = createTestCart('weight-test-1');

        // Add items with weight attribute
        $cart->add([
            'id' => 'item-1',
            'name' => 'Item 1',
            'price' => 100,
            'quantity' => 2,
            'attributes' => ['weight' => 500],
        ]);

        $cart->add([
            'id' => 'item-2',
            'name' => 'Item 2',
            'price' => 200,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $weight = $calculator->getCartWeight($cart);

        expect($weight)->toBe(2000); // (2 * 500) + (1 * 1000)
    });

    it('returns zero weight for empty cart', function (): void {
        $cart = createTestCart('empty-weight');

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $weight = $calculator->getCartWeight($cart);

        expect($weight)->toBe(0);
    });

    it('handles items without weight attribute', function (): void {
        $cart = createTestCart('no-weight-attr');
        $cart->add([
            'id' => 'no-weight-item',
            'name' => 'No Weight',
            'price' => 100,
            'quantity' => 2,
        ]);

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $weight = $calculator->getCartWeight($cart);

        expect($weight)->toBe(0);
    });

    it('calculates shipping for peninsular Malaysia', function (): void {
        $cart = createTestCart('peninsular-test');
        $cart->add([
            'id' => 'weighted-item',
            'name' => 'Weighted Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1500],
        ]);

        $destination = new AddressData(
            name: 'Test Receiver',
            phone: '0123456789',
            address: '456 Test Avenue',
            postCode: '50000',
            state: 'Kuala Lumpur',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->not->toBeNull();
        expect($quote['service_name'])->toBe('J&T Express');
        expect($quote['service_type'])->toBe('EZ');
        expect($quote['weight_kg'])->toBe(1.5);
        expect($quote['estimated_days'])->toBe(3);
        expect($quote['amount'])->toBeGreaterThanOrEqual(800);
    });

    it('applies east Malaysia multiplier for Sabah', function (): void {
        $cart = createTestCart('sabah-test');
        $cart->add([
            'id' => 'sabah-item',
            'name' => 'Sabah Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $peninsularDest = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
            state: 'Kuala Lumpur',
        );

        $sabahDest = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '88000',
            state: 'Sabah',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());

        $peninsularQuote = $calculator->calculateShipping($cart, $peninsularDest);
        $sabahQuote = $calculator->calculateShipping($cart, $sabahDest);

        expect($sabahQuote['amount'])->toBeGreaterThan($peninsularQuote['amount']);
        expect($sabahQuote['estimated_days'])->toBe(5); // 3 + 2 for East Malaysia
    });

    it('returns null when cart has zero weight', function (): void {
        $cart = createTestCart('zero-weight');

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->toBeNull();
    });

    it('returns null when origin address not configured', function (): void {
        config(['jnt.shipping.origin' => null]);

        $cart = createTestCart('no-origin');
        $cart->add([
            'id' => 'origin-test',
            'name' => 'Origin Test',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->toBeNull();
    });

    it('includes quote ID and timestamp', function (): void {
        $cart = createTestCart('quote-id-test');
        $cart->add([
            'id' => 'quote-test-item',
            'name' => 'Quote Test',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->toHaveKey('quote_id');
        expect($quote)->toHaveKey('calculated_at');
        expect($quote)->toHaveKey('cart_weight');
        expect($quote['quote_id'])->toStartWith('jnt_quote_');
    });
});

describe('JntShippingCalculator Weight-Based Pricing', function (): void {
    beforeEach(function (): void {
        config([
            'jnt.shipping.base_rate' => 800,
            'jnt.shipping.per_kg_rate' => 200,
            'jnt.shipping.min_charge' => 800,
            'jnt.shipping.origin' => [
                'name' => 'Test',
                'phone' => '0123456789',
                'address' => 'Test',
                'post_code' => '50000',
            ],
        ]);
    });

    it('charges base rate for first kg', function (): void {
        $cart = createTestCart('base-rate-test');
        $cart->add([
            'id' => 'base-item',
            'name' => 'Base Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 800],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote['amount'])->toBe(800);
    });

    it('adds per-kg rate for additional weight', function (): void {
        $cart = createTestCart('per-kg-test');
        $cart->add([
            'id' => 'heavy-item',
            'name' => 'Heavy Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 2500],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        // 2.5kg rounds up to 3kg: base(800) + 2*perKg(200) = 1200
        expect($quote['amount'])->toBe(1200);
    });

    it('enforces minimum charge', function (): void {
        config([
            'jnt.shipping.base_rate' => 500,
            'jnt.shipping.min_charge' => 800,
        ]);

        $cart = createTestCart('min-charge-test');
        $cart->add([
            'id' => 'light-item',
            'name' => 'Light Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 100],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator(createTestJntExpressService());
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote['amount'])->toBe(800);
    });
});
