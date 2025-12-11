<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\Segment;

describe('Address Model', function (): void {
    describe('Address Creation', function (): void {
        it('can create an address for a customer', function (): void {
            $customer = Customer::create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $address = Address::create([
                'customer_id' => $customer->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_line_1' => '123 Main Street',
                'city' => 'Kuala Lumpur',
                'state' => 'KL',
                'postal_code' => '50000',
                'country' => 'MY',
                'type' => 'shipping',
            ]);

            expect($address)->toBeInstanceOf(Address::class)
                ->and($address->city)->toBe('Kuala Lumpur');
        });
    });

    describe('Address Types', function (): void {
        it('can create shipping and billing addresses', function (): void {
            $customer = Customer::create([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $shipping = Address::create([
                'customer_id' => $customer->id,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address_line_1' => '100 Ship Street',
                'city' => 'Petaling Jaya',
                'country' => 'MY',
                'type' => 'shipping',
            ]);

            $billing = Address::create([
                'customer_id' => $customer->id,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address_line_1' => '200 Bill Street',
                'city' => 'Shah Alam',
                'country' => 'MY',
                'type' => 'billing',
            ]);

            expect($shipping->type)->toBe(AIArmada\Customers\Enums\AddressType::Shipping)
                ->and($billing->type)->toBe(AIArmada\Customers\Enums\AddressType::Billing);
        });
    });

    describe('Default Address', function (): void {
        it('can set default address', function (): void {
            $customer = Customer::create([
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'email' => 'bob-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $default = Address::create([
                'customer_id' => $customer->id,
                'address_line_1' => 'Default Street',
                'city' => 'KL',
                'country' => 'MY',
                'is_default' => true,
            ]);

            $other = Address::create([
                'customer_id' => $customer->id,
                'address_line_1' => 'Other Street',
                'city' => 'KL',
                'country' => 'MY',
                'is_default' => false,
            ]);

            expect($default->is_default)->toBeTrue()
                ->and($other->is_default)->toBeFalse();
        });
    });
});

describe('CustomerGroup Model', function (): void {
    it('can create a customer group', function (): void {
        $group = CustomerGroup::create([
            'name' => 'VIP Customers',
            'description' => 'Customers with high lifetime value',
            'is_active' => true,
        ]);

        expect($group)->toBeInstanceOf(CustomerGroup::class)
            ->and($group->name)->toBe('VIP Customers');
    });

    it('can have multiple customers', function (): void {
        $group = CustomerGroup::create([
            'name' => 'Wholesale',
            'is_active' => true,
        ]);

        $customer1 = Customer::create([
            'first_name' => 'Customer',
            'last_name' => 'One',
            'email' => 'cust1-group-' . uniqid() . '@test.com',
            'status' => CustomerStatus::Active,
        ]);

        $customer2 = Customer::create([
            'first_name' => 'Customer',
            'last_name' => 'Two',
            'email' => 'cust2-group-' . uniqid() . '@test.com',
            'status' => CustomerStatus::Active,
        ]);

        $group->members()->attach([$customer1->id, $customer2->id]);

        expect($group->members)->toHaveCount(2);
    });

    it('can filter active groups', function (): void {
        CustomerGroup::create(['name' => 'Active Group', 'is_active' => true]);
        CustomerGroup::create(['name' => 'Inactive Group', 'is_active' => false]);

        expect(CustomerGroup::active()->count())->toBeGreaterThanOrEqual(1);
    });
});

describe('Segment Model', function (): void {
    it('can create a segment', function (): void {
        $segment = Segment::create([
            'name' => 'High Spenders',
            'description' => 'Customers who spent over RM10,000',
            'is_active' => true,
        ]);

        expect($segment)->toBeInstanceOf(Segment::class)
            ->and($segment->name)->toBe('High Spenders');
    });

    it('can store JSON conditions', function (): void {
        $conditions = [
            ['field' => 'lifetime_value_min', 'value' => 10000],
            ['field' => 'total_orders_min', 'value' => 5],
        ];

        $segment = Segment::create([
            'name' => 'Rule-based Segment',
            'conditions' => $conditions,
            'is_active' => true,
        ]);

        expect($segment->conditions)->toBe($conditions);
    });

    it('can filter active segments', function (): void {
        Segment::create(['name' => 'Active Segment', 'is_active' => true]);
        Segment::create(['name' => 'Inactive Segment', 'is_active' => false]);

        expect(Segment::active()->count())->toBeGreaterThanOrEqual(1);
    });
});
