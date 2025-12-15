<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;

describe('CustomerGroup Model', function (): void {
    describe('Table Name', function (): void {
        it('returns configured table name', function (): void {
            $group = new CustomerGroup();
            expect($group->getTable())->toBeString();
        });
    });

    describe('Casts', function (): void {
        it('has correct casts', function (): void {
            $group = new CustomerGroup();
            $casts = $group->getCasts();

            expect(array_key_exists('spending_limit', $casts))->toBeTrue()
                ->and(array_key_exists('is_active', $casts))->toBeTrue()
                ->and(array_key_exists('requires_approval', $casts))->toBeTrue()
                ->and(array_key_exists('settings', $casts))->toBeTrue()
                ->and(array_key_exists('metadata', $casts))->toBeTrue();
        });
    });

    describe('Default Attributes', function (): void {
        it('defaults to active', function (): void {
            $group = new CustomerGroup();
            expect($group->is_active)->toBeTrue();
        });

        it('defaults to requires approval', function (): void {
            $group = new CustomerGroup();
            expect($group->requires_approval)->toBeTrue();
        });
    });

    describe('Relationships', function (): void {
        it('has members relationship', function (): void {
            $group = new CustomerGroup();
            expect($group->members())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });

        it('has admins relationship', function (): void {
            $group = new CustomerGroup();
            expect($group->admins())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });
    });

    describe('Spending Limit', function (): void {
        it('returns null for unlimited spending', function (): void {
            $group = new CustomerGroup(['spending_limit' => null]);
            expect($group->getRemainingSpendingLimit())->toBeNull();
        });
    });

    describe('Scopes', function (): void {
        it('has active scope', function (): void {
            $query = CustomerGroup::active();
            expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
        });
    });

    describe('Database Operations', function (): void {
        it('can create a group', function (): void {
            $group = CustomerGroup::create([
                'name' => 'Test Group ' . uniqid(),
            ]);

            expect($group)->toBeInstanceOf(CustomerGroup::class)
                ->and($group->id)->not->toBeEmpty();
        });

        it('can have members', function (): void {
            $group = CustomerGroup::create(['name' => 'With Members ' . uniqid()]);

            $customer = Customer::create([
                'first_name' => 'Group',
                'last_name' => 'Member',
                'email' => 'group-member-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $group->members()->attach($customer->id, [
                'role' => 'member',
                'joined_at' => now(),
            ]);

            expect($group->members)->toHaveCount(1)
                ->and($group->members->first()->id)->toBe($customer->id);
        });

        it('can add a member', function (): void {
            $group = CustomerGroup::create(['name' => 'Add Member ' . uniqid()]);

            $customer = Customer::create([
                'first_name' => 'Add',
                'last_name' => 'Member',
                'email' => 'add-member-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $group->addMember($customer);

            expect($group->hasMember($customer))->toBeTrue();
        });

        it('can remove a member', function (): void {
            $group = CustomerGroup::create(['name' => 'Remove Member ' . uniqid()]);

            $customer = Customer::create([
                'first_name' => 'Remove',
                'last_name' => 'Member',
                'email' => 'remove-member-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $group->addMember($customer);
            $group->removeMember($customer);

            expect($group->hasMember($customer))->toBeFalse();
        });

        it('can promote to admin', function (): void {
            $group = CustomerGroup::create(['name' => 'Promote ' . uniqid()]);

            $customer = Customer::create([
                'first_name' => 'Promote',
                'last_name' => 'Admin',
                'email' => 'promote-admin-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $group->addMember($customer, 'member');
            $group->promoteToAdmin($customer);

            expect($group->isAdmin($customer))->toBeTrue();
        });

        it('can demote to member', function (): void {
            $group = CustomerGroup::create(['name' => 'Demote ' . uniqid()]);

            $customer = Customer::create([
                'first_name' => 'Demote',
                'last_name' => 'Member',
                'email' => 'demote-member-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $group->addMember($customer, 'admin');
            $group->demoteToMember($customer);

            expect($group->isAdmin($customer))->toBeFalse()
                ->and($group->hasMember($customer))->toBeTrue();
        });
    });
});
