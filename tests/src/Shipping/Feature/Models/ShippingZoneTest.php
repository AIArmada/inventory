<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;

describe('ShippingZone Model', function (): void {
    it('can create a shipping zone with required fields', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US', 'CA'],
        ]);

        expect($zone)->toBeInstanceOf(ShippingZone::class);
        expect($zone->id)->toBeString();
        expect($zone->name)->toBe('Test Zone');
        expect($zone->code)->toBe('TEST_ZONE');
        expect($zone->type)->toBe('country');
        expect($zone->countries)->toBe(['US', 'CA']);
        expect($zone->priority)->toBe(0);
        expect($zone->is_default)->toBeFalse();
        expect($zone->active)->toBeTrue();
    });

    it('casts attributes correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
            'states' => ['CA', 'NY'],
            'postcode_ranges' => [['from' => '10001', 'to' => '10010']],
            'center_lat' => 40.7128,
            'center_lng' => -74.0060,
            'radius_km' => 50,
            'priority' => 10,
            'is_default' => true,
            'active' => false,
        ]);

        expect($zone->countries)->toBeArray();
        expect($zone->states)->toBeArray();
        expect($zone->postcode_ranges)->toBeArray();
        expect($zone->center_lat)->toBeFloat();
        expect($zone->center_lng)->toBeFloat();
        expect($zone->radius_km)->toBeInt();
        expect($zone->priority)->toBeInt();
        expect($zone->is_default)->toBeBool();
        expect($zone->active)->toBeBool();
    });

    it('has correct relationships', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        expect($zone->rates())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('can create shipping rates', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = $zone->rates()->create([
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Standard Shipping',
            'base_rate' => 1000,
            'calculation_type' => 'flat',
        ]);

        expect($rate)->toBeInstanceOf(ShippingRate::class);
        expect($zone->rates)->toHaveCount(1);
        expect($zone->rates->first()->name)->toBe('Standard Shipping');
    });

    it('has active scope', function (): void {
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Active Zone',
            'code' => 'ACTIVE',
            'type' => 'country',
            'countries' => ['US'],
            'active' => true,
        ]);

        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Inactive Zone',
            'code' => 'INACTIVE',
            'type' => 'country',
            'countries' => ['US'],
            'active' => false,
        ]);

        $activeZones = ShippingZone::active()->get();
        expect($activeZones)->toHaveCount(1);
        expect($activeZones->first()->name)->toBe('Active Zone');
    });

    it('has ordered scope', function (): void {
        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Low Priority',
            'code' => 'LOW',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 1,
        ]);

        ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'High Priority',
            'code' => 'HIGH',
            'type' => 'country',
            'countries' => ['US'],
            'priority' => 10,
        ]);

        $orderedZones = ShippingZone::ordered()->get();
        expect($orderedZones)->toHaveCount(2);
        expect($orderedZones->first()->name)->toBe('High Priority');
        expect($orderedZones->last()->name)->toBe('Low Priority');
    });

    it('matches country addresses correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US Zone',
            'code' => 'US',
            'type' => 'country',
            'countries' => ['US', 'CA'],
        ]);

        $usAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $caAddress = new AddressData(
            name: 'Jane Doe',
            phone: '456-789-0123',
            line1: '456 Maple Ave',
            postcode: 'M5V 1A1',
            country: 'CA',
            city: 'Toronto',
            state: 'ON'
        );

        $ukAddress = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 High St',
            postcode: 'SW1A 1AA',
            country: 'GB',
            city: 'London'
        );

        expect($zone->matchesAddress($usAddress))->toBeTrue();
        expect($zone->matchesAddress($caAddress))->toBeTrue();
        expect($zone->matchesAddress($ukAddress))->toBeFalse();
    });

    it('matches state addresses correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'California Zone',
            'code' => 'CA',
            'type' => 'state',
            'countries' => ['US'],
            'states' => ['CA', 'NV'],
        ]);

        $caAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '90210',
            country: 'US',
            city: 'Los Angeles',
            state: 'CA'
        );

        $nyAddress = new AddressData(
            name: 'Jane Doe',
            phone: '456-789-0123',
            line1: '456 Broadway',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $caCanadaAddress = new AddressData(
            name: 'Bob Smith',
            phone: '789-012-3456',
            line1: '789 Queen St',
            postcode: 'M5V 1A1',
            country: 'CA',
            city: 'Toronto',
            state: 'ON'
        );

        expect($zone->matchesAddress($caAddress))->toBeTrue();
        expect($zone->matchesAddress($nyAddress))->toBeFalse();
        expect($zone->matchesAddress($caCanadaAddress))->toBeFalse();
    });

    it('matches postcode addresses correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Manhattan Zone',
            'code' => 'MANHATTAN',
            'type' => 'postcode',
            'postcode_ranges' => [
                ['from' => '10001', 'to' => '10040'],
                ['from' => '10060', 'to' => '10065'],
            ],
        ]);

        $manhattanAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10025',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        $brooklynAddress = new AddressData(
            name: 'Jane Doe',
            phone: '456-789-0123',
            line1: '456 Flatbush Ave',
            postcode: '11201',
            country: 'US',
            city: 'Brooklyn',
            state: 'NY'
        );

        expect($zone->matchesAddress($manhattanAddress))->toBeTrue();
        expect($zone->matchesAddress($brooklynAddress))->toBeFalse();
    });

    it('matches radius addresses correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Downtown Zone',
            'code' => 'DOWNTOWN',
            'type' => 'radius',
            'center_lat' => 40.7128, // NYC coordinates
            'center_lng' => -74.0060,
            'radius_km' => 10,
        ]);

        $nearbyAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY',
            latitude: 40.7505, // ~4km from center
            longitude: -73.9934
        );

        $farAddress = new AddressData(
            name: 'Jane Doe',
            phone: '456-789-0123',
            line1: '456 Far St',
            postcode: '19101',
            country: 'US',
            city: 'Philadelphia',
            state: 'PA',
            latitude: 39.9526, // ~150km from NYC
            longitude: -75.1652
        );

        expect($zone->matchesAddress($nearbyAddress))->toBeTrue();
        expect($zone->matchesAddress($farAddress))->toBeFalse();
    });

    it('matches entire country when state zone has no states specified', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'US State Zone',
            'code' => 'US_ALL',
            'type' => 'state',
            'countries' => ['US'],
            'states' => [], // No states specified
        ]);

        $anyUsAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        expect($zone->matchesAddress($anyUsAddress))->toBeTrue();
    });

    it('matches state zone with null state in address', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'California Zone',
            'code' => 'CA',
            'type' => 'state',
            'countries' => ['US'],
            'states' => ['CA'],
        ]);

        $noStateAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '90210',
            country: 'US',
            city: 'Los Angeles',
            state: null // No state provided
        );

        expect($zone->matchesAddress($noStateAddress))->toBeFalse();
    });

    it('does not match postcode zone with empty postcode', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Manhattan Zone',
            'code' => 'MANHATTAN',
            'type' => 'postcode',
            'postcode_ranges' => [
                ['from' => '10001', 'to' => '10040'],
            ],
        ]);

        $noPostcodeAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '', // Empty postcode
            country: 'US',
            city: 'New York',
            state: 'NY'
        );

        expect($zone->matchesAddress($noPostcodeAddress))->toBeFalse();
    });

    it('does not match radius zone without address coordinates', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Downtown Zone',
            'code' => 'DOWNTOWN',
            'type' => 'radius',
            'center_lat' => 40.7128,
            'center_lng' => -74.0060,
            'radius_km' => 10,
        ]);

        $noCoordinatesAddress = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY',
            latitude: null, // No coordinates
            longitude: null
        );

        expect($zone->matchesAddress($noCoordinatesAddress))->toBeFalse();
    });

    it('does not match radius zone with incomplete center coordinates', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Incomplete Zone',
            'code' => 'INCOMPLETE',
            'type' => 'radius',
            'center_lat' => null, // Missing center coordinates
            'center_lng' => null,
            'radius_km' => 10,
        ]);

        $addressWithCoords = new AddressData(
            name: 'John Doe',
            phone: '123-456-7890',
            line1: '123 Main St',
            postcode: '10001',
            country: 'US',
            city: 'New York',
            state: 'NY',
            latitude: 40.7505,
            longitude: -73.9934
        );

        expect($zone->matchesAddress($addressWithCoords))->toBeFalse();
    });

    it('returns false for unknown zone type', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Unknown Zone',
            'code' => 'UNKNOWN',
            'type' => 'unknown_type',
            'countries' => ['US'],
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

        expect($zone->matchesAddress($address))->toBeFalse();
    });

    it('does not match country zone with empty countries array', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Empty Zone',
            'code' => 'EMPTY',
            'type' => 'country',
            'countries' => [],
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

        expect($zone->matchesAddress($address))->toBeFalse();
    });

    it('does not match postcode zone with empty ranges', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Empty Postcode Zone',
            'code' => 'EMPTY_PC',
            'type' => 'postcode',
            'postcode_ranges' => [],
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

        expect($zone->matchesAddress($address))->toBeFalse();
    });
});
