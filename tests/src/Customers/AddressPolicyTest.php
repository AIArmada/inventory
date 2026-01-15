<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Enums\AddressType;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Policies\AddressPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

function bindAddressPolicyOwnerResolver(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    $this->policy = new AddressPolicy;
    $this->user = new class
    {
        public string $id = 'user-a';
    };
});

describe('AddressPolicy', function (): void {
    describe('viewAny', function (): void {
        it('allows viewing any addresses when authenticated', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });

        it('denies viewing any addresses when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global address without owner resolver', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $address = Address::query()->create([
                'customer_id' => $customer->id,
                'type' => AddressType::Shipping,
                'address_line_1' => '123 Test St',
                'city' => 'Test City',
                'postcode' => '12345',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->view($this->user, $address))->toBeTrue();
        });

        it('denies viewing owner-scoped address without owner resolver', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);

            $customer = Customer::query()->create([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);

            $address = Address::query()->create([
                'customer_id' => $customer->id,
                'type' => AddressType::Shipping,
                'address_line_1' => '123 Test St',
                'city' => 'Test City',
                'postcode' => '12345',
                'country' => 'MY',
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);

            expect($this->policy->view($this->user, $address))->toBeFalse();
        });

        it('denies cross-tenant address access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $customerA = Customer::query()->create([
                'first_name' => 'A',
                'last_name' => 'Customer',
                'email' => 'a-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ]);

            $customerB = Customer::query()->create([
                'first_name' => 'B',
                'last_name' => 'Customer',
                'email' => 'b-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ]);

            $addressA = Address::query()->create([
                'customer_id' => $customerA->id,
                'type' => AddressType::Shipping,
                'address_line_1' => '123 A St',
                'city' => 'A City',
                'postcode' => '11111',
                'country' => 'MY',
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ]);

            $addressB = Address::query()->create([
                'customer_id' => $customerB->id,
                'type' => AddressType::Shipping,
                'address_line_1' => '456 B St',
                'city' => 'B City',
                'postcode' => '22222',
                'country' => 'MY',
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ]);

            bindAddressPolicyOwnerResolver($ownerA);

            expect($this->policy->view($this->user, $addressA))->toBeTrue()
                ->and($this->policy->view($this->user, $addressB))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating addresses when authenticated', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });

        it('denies creating addresses when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global address without owner resolver', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $address = Address::query()->create([
                'customer_id' => $customer->id,
                'type' => AddressType::Billing,
                'address_line_1' => '789 Update St',
                'city' => 'Update City',
                'postcode' => '33333',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->update($this->user, $address))->toBeTrue();
        });

        it('denies updating address when unauthenticated', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-unauth-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $address = Address::query()->create([
                'customer_id' => $customer->id,
                'type' => AddressType::Billing,
                'address_line_1' => '789 Update St',
                'city' => 'Update City',
                'postcode' => '33333',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->update(null, $address))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global address without owner resolver', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $address = Address::query()->create([
                'customer_id' => $customer->id,
                'type' => AddressType::Both,
                'address_line_1' => '999 Delete St',
                'city' => 'Delete City',
                'postcode' => '44444',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->delete($this->user, $address))->toBeTrue();
        });

        it('denies deleting address when unauthenticated', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-unauth-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $address = Address::query()->create([
                'customer_id' => $customer->id,
                'type' => AddressType::Both,
                'address_line_1' => '999 Delete St',
                'city' => 'Delete City',
                'postcode' => '44444',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->delete(null, $address))->toBeFalse();
        });
    });
});
