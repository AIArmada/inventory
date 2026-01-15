<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Policies\CustomerNotePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

function bindNotePolicyOwnerResolver(?Model $owner): void
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

    $this->policy = new CustomerNotePolicy;
    $this->user = new class
    {
        public string $id = 'user-a';
    };
});

describe('CustomerNotePolicy', function (): void {
    describe('viewAny', function (): void {
        it('allows viewing any notes when authenticated', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });

        it('denies viewing any notes when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global note without owner resolver', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $note = CustomerNote::query()->create([
                'customer_id' => $customer->id,
                'content' => 'Test note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->view($this->user, $note))->toBeTrue();
        });

        it('denies viewing owner-scoped note without owner resolver', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);

            $customer = Customer::query()->create([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);

            $note = CustomerNote::query()->create([
                'customer_id' => $customer->id,
                'content' => 'Owned note content',
                'is_internal' => true,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);

            expect($this->policy->view($this->user, $note))->toBeFalse();
        });

        it('denies cross-tenant note access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $customerA = Customer::query()->create([
                'first_name' => 'A',
                'last_name' => 'Customer',
                'email' => 'a-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ]);

            $customerB = Customer::query()->create([
                'first_name' => 'B',
                'last_name' => 'Customer',
                'email' => 'b-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ]);

            $noteA = CustomerNote::query()->create([
                'customer_id' => $customerA->id,
                'content' => 'Note for A',
                'is_internal' => true,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ]);

            $noteB = CustomerNote::query()->create([
                'customer_id' => $customerB->id,
                'content' => 'Note for B',
                'is_internal' => true,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ]);

            bindNotePolicyOwnerResolver($ownerA);

            expect($this->policy->view($this->user, $noteA))->toBeTrue()
                ->and($this->policy->view($this->user, $noteB))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating notes when authenticated', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });

        it('denies creating notes when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global note without owner resolver', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $note = CustomerNote::query()->create([
                'customer_id' => $customer->id,
                'content' => 'Update note content',
                'is_internal' => false,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->update($this->user, $note))->toBeTrue();
        });

        it('denies updating note when unauthenticated', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-unauth-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $note = CustomerNote::query()->create([
                'customer_id' => $customer->id,
                'content' => 'Update note content',
                'is_internal' => false,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->update(null, $note))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global note without owner resolver', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $note = CustomerNote::query()->create([
                'customer_id' => $customer->id,
                'content' => 'Delete note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->delete($this->user, $note))->toBeTrue();
        });

        it('denies deleting note when unauthenticated', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-unauth-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $note = CustomerNote::query()->create([
                'customer_id' => $customer->id,
                'content' => 'Delete note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->delete(null, $note))->toBeFalse();
        });
    });
});
