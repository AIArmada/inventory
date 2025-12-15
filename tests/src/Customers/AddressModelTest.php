<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\AddressType;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;

describe('Address Model', function (): void {
    beforeEach(function (): void {
        $this->customer = Customer::create([
            'first_name' => 'Address',
            'last_name' => 'Test',
            'email' => 'address-' . uniqid() . '@example.com',
            'status' => CustomerStatus::Active,
        ]);
    });

    describe('Table Name', function (): void {
        it('returns configured table name', function (): void {
            $address = new Address();
            expect($address->getTable())->toBeString();
        });
    });

    describe('Casts', function (): void {
        it('has type cast', function (): void {
            $address = new Address();
            $casts = $address->getCasts();
            expect(array_key_exists('type', $casts))->toBeTrue();
        });

        it('has boolean casts', function (): void {
            $address = new Address();
            $casts = $address->getCasts();
            expect(array_key_exists('is_default_billing', $casts))->toBeTrue()
                ->and(array_key_exists('is_default_shipping', $casts))->toBeTrue()
                ->and(array_key_exists('is_verified', $casts))->toBeTrue();
        });
    });

    describe('Relationships', function (): void {
        it('belongs to a customer', function (): void {
            $address = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Main St',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            expect($address->customer)->toBeInstanceOf(Customer::class)
                ->and($address->customer->id)->toBe($this->customer->id);
        });
    });

    describe('Type Helpers', function (): void {
        it('checks if billing address', function (): void {
            $billing = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Billing St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Billing,
            ]);

            $shipping = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '456 Shipping St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Shipping,
            ]);

            $both = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '789 Both St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Both,
            ]);

            expect($billing->isBillingAddress())->toBeTrue()
                ->and($both->isBillingAddress())->toBeTrue()
                ->and($shipping->isBillingAddress())->toBeFalse();
        });

        it('checks if shipping address', function (): void {
            $billing = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Billing St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Billing,
            ]);

            $shipping = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '456 Shipping St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Shipping,
            ]);

            expect($shipping->isShippingAddress())->toBeTrue()
                ->and($billing->isShippingAddress())->toBeFalse();
        });
    });

    describe('Default Management', function (): void {
        it('can set as default billing', function (): void {
            $address1 = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Main St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'is_default_billing' => true,
            ]);

            $address2 = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '456 Other St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            $address2->setAsDefaultBilling();

            expect($address2->fresh()->is_default_billing)->toBeTrue()
                ->and($address1->fresh()->is_default_billing)->toBeFalse();
        });

        it('can set as default shipping', function (): void {
            $address1 = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Main St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'is_default_shipping' => true,
            ]);

            $address2 = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '456 Other St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            $address2->setAsDefaultShipping();

            expect($address2->fresh()->is_default_shipping)->toBeTrue()
                ->and($address1->fresh()->is_default_shipping)->toBeFalse();
        });
    });

    describe('Full Address Attribute', function (): void {
        it('returns concatenated address parts', function (): void {
            $address = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Main St',
                'address_line_2' => 'Suite 100',
                'city' => 'Kuala Lumpur',
                'state' => 'WP',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            expect($address->full_address)->toContain('123 Main St')
                ->and($address->full_address)->toContain('Kuala Lumpur')
                ->and($address->full_address)->toContain('MY');
        });
    });

    describe('Formatted Address', function (): void {
        it('returns multi-line formatted address', function (): void {
            $address = Address::create([
                'customer_id' => $this->customer->id,
                'recipient_name' => 'John Doe',
                'company' => 'Acme Inc',
                'address_line_1' => '123 Main St',
                'address_line_2' => 'Suite 100',
                'city' => 'Kuala Lumpur',
                'state' => 'WP',
                'postcode' => '50000',
                'country' => 'MY',
                'phone' => '+60123456789',
            ]);

            $formatted = $address->getFormattedAddress();

            expect($formatted)->toContain('John Doe')
                ->and($formatted)->toContain('Acme Inc')
                ->and($formatted)->toContain('123 Main St')
                ->and($formatted)->toContain('+60123456789');
        });
    });

    describe('Shipping Label', function (): void {
        it('returns array for shipping label', function (): void {
            $address = Address::create([
                'customer_id' => $this->customer->id,
                'recipient_name' => 'Jane Doe',
                'company' => 'Test Corp',
                'address_line_1' => '123 Main St',
                'address_line_2' => 'Apt 4B',
                'city' => 'KL',
                'state' => 'WP',
                'postcode' => '50000',
                'country' => 'MY',
                'phone' => '+60123456789',
            ]);

            $label = $address->toShippingLabel();

            expect($label)->toBeArray()
                ->and($label['name'])->toBe('Jane Doe')
                ->and($label['company'])->toBe('Test Corp')
                ->and($label['address1'])->toBe('123 Main St')
                ->and($label['city'])->toBe('KL');
        });

        it('falls back to customer name when no recipient', function (): void {
            $address = Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Main St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            $label = $address->toShippingLabel();

            expect($label['name'])->toBe('Address Test');
        });
    });

    describe('Scopes', function (): void {
        it('can filter billing addresses', function (): void {
            Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Billing',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Billing,
            ]);

            Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '456 Shipping',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Shipping,
            ]);

            $billing = Address::billing()->get();

            expect($billing->every(fn ($a) => $a->isBillingAddress()))->toBeTrue();
        });

        it('can filter shipping addresses', function (): void {
            Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '456 Shipping',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'type' => AddressType::Shipping,
            ]);

            $shipping = Address::shipping()->get();

            expect($shipping->every(fn ($a) => $a->isShippingAddress()))->toBeTrue();
        });

        it('can filter default billing addresses', function (): void {
            Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Default Billing',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'is_default_billing' => true,
            ]);

            $defaultBilling = Address::defaultBilling()->get();

            expect($defaultBilling->every(fn ($a) => $a->is_default_billing))->toBeTrue();
        });

        it('can filter default shipping addresses', function (): void {
            Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Default Shipping',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'is_default_shipping' => true,
            ]);

            $defaultShipping = Address::defaultShipping()->get();

            expect($defaultShipping->every(fn ($a) => $a->is_default_shipping))->toBeTrue();
        });

        it('can filter verified addresses', function (): void {
            Address::create([
                'customer_id' => $this->customer->id,
                'address_line_1' => '123 Verified',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'is_verified' => true,
            ]);

            $verified = Address::verified()->get();

            expect($verified->every(fn ($a) => $a->is_verified))->toBeTrue();
        });
    });
});
