<?php

declare(strict_types=1);

use AIArmada\Customers\Concerns\HasCustomerProfile;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Create a test model that uses the trait (users table uses UUID primary key in the test schema)
class TestUserWithProfile extends Model
{
    use HasCustomerProfile;
    use HasUuids;

    public $timestamps = true;

    protected $table = 'users';

    protected $guarded = [];
}

describe('HasCustomerProfile Trait', function (): void {
    describe('customerProfile Relationship', function (): void {
        it('returns hasOne relationship', function (): void {
            $user = new TestUserWithProfile;
            expect($user->customerProfile())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasOne::class);
        });
    });

    describe('hasCustomerProfile Method', function (): void {
        it('returns false when no profile exists', function (): void {
            // Create a user in the database
            $user = TestUserWithProfile::create([
                'name' => 'Test User',
                'email' => 'test-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            expect($user->hasCustomerProfile())->toBeFalse();
        });

        it('returns true when profile exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'Has Profile',
                'email' => 'has-profile-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            // Create customer profile for this user
            Customer::create([
                'user_id' => $user->id,
                'first_name' => 'Has',
                'last_name' => 'Profile',
                'email' => $user->email,
                'status' => CustomerStatus::Active,
            ]);

            expect($user->hasCustomerProfile())->toBeTrue();
        });
    });

    describe('getOrCreateCustomerProfile Method', function (): void {
        it('returns existing profile when it exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'Existing Profile',
                'email' => 'existing-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            $existingCustomer = Customer::create([
                'user_id' => $user->id,
                'first_name' => 'Existing',
                'last_name' => 'Customer',
                'email' => $user->email,
                'status' => CustomerStatus::Active,
            ]);

            $profile = $user->getOrCreateCustomerProfile();

            expect($profile->id)->toBe($existingCustomer->id);
        });

        it('creates new profile when none exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'Jane Doe',
                'email' => 'new-profile-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            $profile = $user->getOrCreateCustomerProfile();

            expect($profile)->toBeInstanceOf(Customer::class)
                ->and($profile->email)->toBe($user->email)
                ->and($profile->first_name)->toBe('Jane')
                ->and($profile->last_name)->toBe('Doe');
        });
    });

    describe('acceptsMarketing Method', function (): void {
        it('returns false when no profile exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'No Marketing',
                'email' => 'no-mktg-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            expect($user->acceptsMarketing())->toBeFalse();
        });

        it('returns marketing preference when profile exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'Yes Marketing',
                'email' => 'yes-mktg-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            Customer::create([
                'user_id' => $user->id,
                'first_name' => 'Yes',
                'last_name' => 'Marketing',
                'email' => $user->email,
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            expect($user->acceptsMarketing())->toBeTrue();
        });
    });

    describe('getDefaultShippingAddress Method', function (): void {
        it('returns null when no profile exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'No Address',
                'email' => 'no-addr-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            expect($user->getDefaultShippingAddress())->toBeNull();
        });
    });

    describe('getDefaultBillingAddress Method', function (): void {
        it('returns null when no profile exists', function (): void {
            $user = TestUserWithProfile::create([
                'name' => 'No Billing',
                'email' => 'no-billing-' . uniqid() . '@example.com',
                'password' => 'password',
            ]);

            expect($user->getDefaultBillingAddress())->toBeNull();
        });
    });
});
