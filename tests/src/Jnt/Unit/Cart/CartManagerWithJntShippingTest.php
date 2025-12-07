<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Jnt\Cart\CartManagerWithJntShipping;
use AIArmada\Jnt\Cart\JntShippingCalculator;
use AIArmada\Jnt\Data\AddressData;
use Illuminate\Support\Facades\DB;

describe('CartManagerWithJntShipping', function (): void {
    beforeEach(function (): void {
        config([
            'jnt.shipping.base_rate' => 800,
            'jnt.shipping.per_kg_rate' => 200,
            'jnt.shipping.min_charge' => 800,
            'jnt.shipping.default_estimated_days' => 3,
            'jnt.shipping.origin' => [
                'name' => 'Test Sender',
                'phone' => '0123456789',
                'address' => 'Test Address',
                'post_code' => '50000',
            ],
        ]);

        $this->storage = new DatabaseStorage(DB::connection('testing'), 'carts');
        $this->events = app(Illuminate\Contracts\Events\Dispatcher::class);
        $this->baseManager = new CartManager(
            storage: $this->storage,
            events: $this->events,
            eventsEnabled: true
        );
    });

    it('wraps cart manager via fromCartManager', function (): void {
        $wrapped = CartManagerWithJntShipping::fromCartManager($this->baseManager);

        expect($wrapped)->toBeInstanceOf(CartManagerWithJntShipping::class);
        expect($wrapped)->toBeInstanceOf(CartManagerInterface::class);
    });

    it('returns same instance if already wrapped', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);

        $result = CartManagerWithJntShipping::fromCartManager($wrapped);

        expect($result)->toBe($wrapped);
    });

    it('delegates instance method to underlying manager', function (): void {
        $this->baseManager->setInstance('test-instance');
        $wrapped = new CartManagerWithJntShipping($this->baseManager);

        expect($wrapped->instance())->toBe('test-instance');
    });

    it('delegates setInstance method to underlying manager', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $result = $wrapped->setInstance('wishlist');

        expect($result)->toBe($wrapped);
        expect($wrapped->instance())->toBe('wishlist');
    });

    it('unwraps nested decorators via getBaseManager', function (): void {
        $layer1 = new CartManagerWithJntShipping($this->baseManager);
        $layer2 = new CartManagerWithJntShipping($layer1);

        $result = $layer2->getBaseManager();

        expect($result)->toBe($this->baseManager);
    });

    it('sets and retrieves shipping address via cart metadata', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('shipping-test');

        $address = new AddressData(
            name: 'Test Receiver',
            phone: '0123456789',
            address: 'Test Address',
            postCode: '50000',
        );

        $wrapped->setShippingAddress($address);

        $retrieved = $wrapped->getShippingAddress();

        expect($retrieved)->toBeInstanceOf(AddressData::class);
        expect($retrieved->name)->toBe('Test Receiver');
    });

    it('accepts array for shipping address', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('array-address-test');

        $wrapped->setShippingAddress([
            'name' => 'Array Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
        ]);

        $retrieved = $wrapped->getShippingAddress();

        expect($retrieved)->toBeInstanceOf(AddressData::class);
        expect($retrieved->name)->toBe('Array Test');
    });

    it('clears shipping address and quote', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('clear-test');

        $wrapped->setShippingAddress([
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
        ]);

        expect($wrapped->hasShippingAddress())->toBeTrue();

        $wrapped->clearShippingAddress();

        expect($wrapped->hasShippingAddress())->toBeFalse();
    });

    it('returns null when no shipping address set', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('no-address-test');

        $result = $wrapped->getShippingAddress();

        expect($result)->toBeNull();
    });

    it('checks hasShippingAddress correctly', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('has-address-test');

        expect($wrapped->hasShippingAddress())->toBeFalse();

        $wrapped->setShippingAddress([
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
        ]);

        expect($wrapped->hasShippingAddress())->toBeTrue();
    });

    it('returns null for calculateShipping when no address set', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('calc-no-address');

        $result = $wrapped->calculateShipping();

        expect($result)->toBeNull();
    });

    it('gets estimated delivery days from cached quote', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('estimated-days-test');

        // Set shipping address first
        $wrapped->setShippingAddress([
            'name' => 'Test',
            'phone' => '0123456789',
            'address' => 'Test',
            'postCode' => '50000',
        ]);

        // Manually set a cached quote for testing
        $wrapped->getCurrentCart()->setMetadata('jnt_shipping_quote', [
            'estimated_days' => 5,
            'amount' => 1200,
        ]);

        expect($wrapped->getEstimatedDeliveryDays())->toBe(5);
    });

    it('can set custom calculator', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $jntService = new AIArmada\Jnt\Services\JntExpressService(
            customerCode: 'TEST',
            password: 'test',
            config: [
                'environment' => 'testing',
                'base_urls' => ['testing' => 'https://demo.api.test'],
                'api_account' => 'test',
                'private_key' => 'test',
            ]
        );
        $calculator = new JntShippingCalculator($jntService);

        $result = $wrapped->setCalculator($calculator);

        expect($result)->toBe($wrapped);
        expect($wrapped->getCalculator())->toBe($calculator);
    });

    it('proxies getCurrentCart', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);
        $wrapped->setIdentifier('proxy-cart-test');

        $cart = $wrapped->getCurrentCart();

        expect($cart)->toBeInstanceOf(Cart::class);
    });

    it('proxies forOwner', function (): void {
        $wrapped = new CartManagerWithJntShipping($this->baseManager);

        // Create an anonymous model for testing
        $owner = new class extends Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            public function getKey(): int
            {
                return 1;
            }
        };

        $result = $wrapped->forOwner($owner);

        expect($result)->toBeInstanceOf(CartManagerWithJntShipping::class);
    });
});
