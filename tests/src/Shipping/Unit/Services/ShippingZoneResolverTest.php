<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Models\ShippingZone;
use AIArmada\Shipping\Services\ShippingZoneResolver;

describe('ShippingZoneResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new ShippingZoneResolver;
    });

    it('can resolve zone for matching address', function (): void {
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $zone = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');

        expect($zone)->toBeInstanceOf(ShippingZone::class);
        expect($zone->name)->toBe('US Zone');
    });

    it('falls back to default zone when no match', function (): void {
        // Create a default zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Default Zone',
            'code' => 'default',
            'type' => 'country',
            'countries' => [],
            'priority' => 0,
            'active' => true,
            'is_default' => true,
        ]);

        $address = new AddressData(
            name: 'Jane Doe',
            phone: '456-789-0123',
            line1: '456 Maple Ave',
            postcode: 'M5V 1A1',
            country: 'CA',
            city: 'Toronto',
            state: 'ON'
        );

        $zone = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');

        expect($zone)->toBeInstanceOf(ShippingZone::class);
        expect($zone->name)->toBe('Default Zone');
    });

    it('returns null when no zones match and no default', function (): void {
        $address = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 High St',
            postcode: 'SW1A 1AA',
            country: 'GB',
            city: 'London'
        );

        $zone = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');

        expect($zone)->toBeNull();
    });

    it('caches resolution results', function (): void {
        // Create US zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        // First call
        $zone1 = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');
        // Second call should use cache
        $zone2 = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');

        expect($zone1)->toBe($zone2); // Same instance from cache
        expect($zone1->name)->toBe('US Zone');
    });

    it('can clear cache', function (): void {
        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $zone1 = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');
        $this->resolver->clearCache();
        $zone2 = $this->resolver->resolve($address, 'test-owner-123', 'TestOwner');

        expect($zone1)->toBe($zone2); // Still same due to database state
    });

    it('can get all matching zones', function (): void {
        // Create US zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        // Create default zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Default Zone',
            'code' => 'default',
            'type' => 'country',
            'countries' => [],
            'priority' => 0,
            'active' => true,
            'is_default' => true,
        ]);

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $zones = $this->resolver->resolveAll($address, 'test-owner-123', 'TestOwner');

        expect($zones)->toHaveCount(2); // US Zone + Default Zone
        expect($zones->first()->name)->toBe('US Zone'); // Higher priority first
    });

    it('can check if address is serviceable', function (): void {
        // Create US zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        $serviceableAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $nonServiceableAddress = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 High St',
            postcode: 'SW1A 1AA',
            country: 'GB',
            city: 'London'
        );

        expect($this->resolver->isServiceable($serviceableAddress, 'test-owner-123', 'TestOwner'))->toBeTrue();
        expect($this->resolver->isServiceable($nonServiceableAddress, 'test-owner-123', 'TestOwner'))->toBeFalse();
    });

    it('can test zone matching with details', function (): void {
        // Create US zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $result = $this->resolver->test($address, 'test-owner-123', 'TestOwner');

        expect($result['matched'])->toBeTrue();
        expect($result['zone'])->toBeInstanceOf(ShippingZone::class);
        expect($result['zone']->name)->toBe('US Zone');
        expect($result['reason'])->toContain('country rule');
    });

    it('provides test result for non-matching address', function (): void {
        $address = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 High St',
            postcode: 'SW1A 1AA',
            country: 'GB',
            city: 'London'
        );

        $result = $this->resolver->test($address, 'test-owner-123', 'TestOwner');

        expect($result['matched'])->toBeFalse();
        expect($result['zone'])->toBeNull();
        expect($result['reason'])->toContain('No matching zone found');
    });

    it('provides test result for default zone match', function (): void {
        // Create only default zone
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Default Zone',
            'code' => 'default',
            'type' => 'country',
            'countries' => [],
            'priority' => 0,
            'active' => true,
            'is_default' => true,
        ]);

        $address = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 High St',
            postcode: 'SW1A 1AA',
            country: 'GB',
            city: 'London'
        );

        $result = $this->resolver->test($address, 'test-owner-123', 'TestOwner');

        expect($result['matched'])->toBeTrue();
        expect($result['zone'])->toBeInstanceOf(ShippingZone::class);
        expect($result['zone']->name)->toBe('Default Zone');
        expect($result['reason'])->toContain('default zone');
    });

    it('can get applicable rates for address', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        AIArmada\Shipping\Models\ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => null, // Null carrier code matches all carriers
            'method_code' => 'standard',
            'name' => 'Standard Shipping',
            'calculation_type' => 'flat',
            'base_rate' => 1000,
            'active' => true,
        ]);

        AIArmada\Shipping\Models\ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => null, // Null carrier code matches all carriers
            'method_code' => 'express',
            'name' => 'Express Shipping',
            'calculation_type' => 'flat',
            'base_rate' => 1500,
            'active' => true,
        ]);

        // Create a fresh resolver to avoid cache issues
        $resolver = new ShippingZoneResolver;

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $rates = $resolver->getApplicableRates($address, null, 'test-owner-123', 'TestOwner');

        expect($rates)->toHaveCount(2);
    });

    it('can filter applicable rates by carrier', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        AIArmada\Shipping\Models\ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'fedex',
            'method_code' => 'standard',
            'name' => 'FedEx Standard',
            'calculation_type' => 'flat',
            'base_rate' => 1000,
            'active' => true,
        ]);

        AIArmada\Shipping\Models\ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'ups',
            'method_code' => 'standard',
            'name' => 'UPS Standard',
            'calculation_type' => 'flat',
            'base_rate' => 1200,
            'active' => true,
        ]);

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $rates = $this->resolver->getApplicableRates($address, 'fedex', 'test-owner-123', 'TestOwner');

        expect($rates)->toHaveCount(1);
        expect($rates->first()->carrier_code)->toBe('fedex');
    });

    it('returns empty rates when no zone matches', function (): void {
        $address = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 High St',
            postcode: 'SW1A 1AA',
            country: 'GB',
            city: 'London'
        );

        $rates = $this->resolver->getApplicableRates($address, null, 'test-owner-123', 'TestOwner');

        expect($rates)->toBeEmpty();
    });

    it('resolves global zones when no owner is provided', function (): void {
        ShippingZone::create([
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Global US Zone',
            'code' => 'global-us',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 5,
            'active' => true,
        ]);

        $address = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $zones = $this->resolver->resolveAll($address);

        expect($zones)->toHaveCount(1);
        expect($zones->first()->name)->toBe('Global US Zone');
    });

    it('blocks cross-tenant zone reads when owner scoping is enabled', function (): void {
        config()->set('shipping.features.owner.enabled', true);
        config()->set('shipping.features.owner.include_global', true);

        $ownerA = new class extends \Illuminate\Database\Eloquent\Model
        {
            use \Illuminate\Database\Eloquent\Concerns\HasUuids;

            protected $table = 'test_shipping_owners';

            protected $fillable = ['name'];
        };

        \Illuminate\Support\Facades\Schema::dropIfExists('test_shipping_owners');
        \Illuminate\Support\Facades\Schema::create('test_shipping_owners', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        $ownerA = $ownerA::query()->create(['name' => 'Owner A']);
        $ownerB = $ownerA::query()->create(['name' => 'Owner B']);

        ShippingZone::create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'name' => 'Owner A Zone',
            'code' => 'owner-a-us',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        ShippingZone::create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'Owner B Zone',
            'code' => 'owner-b-us',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
            'active' => true,
        ]);

        ShippingZone::create([
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Global US Zone 2',
            'code' => 'global-us-2',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 1,
            'active' => true,
        ]);

        app()->instance(\AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class, new class($ownerA) implements \AIArmada\CommerceSupport\Contracts\OwnerResolverInterface
        {
            public function __construct(private readonly ?\Illuminate\Database\Eloquent\Model $owner) {}

            public function resolve(): ?\Illuminate\Database\Eloquent\Model
            {
                return $this->owner;
            }
        });

        $address = new \AIArmada\Shipping\Data\AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $zones = $this->resolver->resolveAll($address);

        expect($zones->pluck('name')->all())
            ->toContain('Owner A Zone', 'Global US Zone 2')
            ->not->toContain('Owner B Zone');
    });
});
