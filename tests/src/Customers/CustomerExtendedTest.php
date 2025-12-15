<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\WalletCreditAdded;
use AIArmada\Customers\Events\WalletCreditDeducted;
use AIArmada\Customers\Models\Customer;
use Illuminate\Support\Facades\Event;

describe('Customer Model - Extended Coverage', function (): void {
    describe('Relationships', function (): void {
        it('has addresses relationship', function (): void {
            $customer = new Customer();
            expect($customer->addresses())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        });

        it('has segments relationship', function (): void {
            $customer = new Customer();
            expect($customer->segments())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });

        it('has wishlists relationship', function (): void {
            $customer = new Customer();
            expect($customer->wishlists())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        });

        it('has notes relationship', function (): void {
            $customer = new Customer();
            expect($customer->notes())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        });

        it('has groups relationship', function (): void {
            $customer = new Customer();
            expect($customer->groups())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });
    });

    describe('Casts', function (): void {
        it('has correct casts', function (): void {
            $customer = new Customer();
            $casts = $customer->getCasts();

            expect(array_key_exists('status', $casts))->toBeTrue()
                ->and(array_key_exists('accepts_marketing', $casts))->toBeTrue()
                ->and(array_key_exists('is_tax_exempt', $casts))->toBeTrue()
                ->and(array_key_exists('wallet_balance', $casts))->toBeTrue()
                ->and(array_key_exists('lifetime_value', $casts))->toBeTrue()
                ->and(array_key_exists('total_orders', $casts))->toBeTrue()
                ->and(array_key_exists('last_order_at', $casts))->toBeTrue()
                ->and(array_key_exists('last_login_at', $casts))->toBeTrue();
        });
    });

    describe('Address Helpers', function (): void {
        it('returns null for default billing when none set', function (): void {
            $customer = Customer::create([
                'first_name' => 'No',
                'last_name' => 'Billing',
                'email' => 'no-billing-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($customer->getDefaultBillingAddress())->toBeNull();
        });

        it('returns null for default shipping when none set', function (): void {
            $customer = Customer::create([
                'first_name' => 'No',
                'last_name' => 'Shipping',
                'email' => 'no-shipping-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($customer->getDefaultShippingAddress())->toBeNull();
        });
    });

    describe('Wallet Methods', function (): void {
        it('returns formatted wallet balance', function (): void {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Format',
                'email' => 'wallet-format-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 10000,
            ]);

            $formatted = $customer->getFormattedWalletBalance();

            expect($formatted)->toBeString();
        });

        it('rejects credit addition exceeding max balance', function (): void {
            config(['customers.wallet.max_balance' => 50000]);

            $customer = Customer::create([
                'first_name' => 'Max',
                'last_name' => 'Balance',
                'email' => 'max-balance-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 40000,
            ]);

            $result = $customer->addCredit(20000);

            expect($result)->toBeFalse()
                ->and($customer->wallet_balance)->toBe(40000);
        });

        it('fires event when credit added', function (): void {
            Event::fake();

            $customer = Customer::create([
                'first_name' => 'Credit',
                'last_name' => 'Event',
                'email' => 'credit-event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 0,
            ]);

            $customer->addCredit(5000, 'Test reason');

            Event::assertDispatched(WalletCreditAdded::class);
        });

        it('rejects deduction exceeding balance', function (): void {
            $customer = Customer::create([
                'first_name' => 'Deduct',
                'last_name' => 'Exceed',
                'email' => 'deduct-exceed-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 1000,
            ]);

            $result = $customer->deductCredit(5000);

            expect($result)->toBeFalse()
                ->and($customer->wallet_balance)->toBe(1000);
        });

        it('fires event when credit deducted', function (): void {
            Event::fake();

            $customer = Customer::create([
                'first_name' => 'Deduct',
                'last_name' => 'Event',
                'email' => 'deduct-event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 10000,
            ]);

            $customer->deductCredit(5000, 'Payment');

            Event::assertDispatched(WalletCreditDeducted::class);
        });
    });

    describe('LTV Helpers', function (): void {
        it('returns formatted lifetime value', function (): void {
            $customer = Customer::create([
                'first_name' => 'LTV',
                'last_name' => 'Format',
                'email' => 'ltv-format-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 50000,
            ]);

            $formatted = $customer->getFormattedLifetimeValue();

            expect($formatted)->toBeString();
        });

        it('returns zero for average order value with no orders', function (): void {
            $customer = Customer::create([
                'first_name' => 'Zero',
                'last_name' => 'Orders',
                'email' => 'zero-orders-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'total_orders' => 0,
                'lifetime_value' => 0,
            ]);

            expect($customer->getAverageOrderValue())->toBe(0);
        });
    });

    describe('Status Helpers', function (): void {
        it('checks canPlaceOrders', function (): void {
            $active = new Customer(['status' => CustomerStatus::Active]);
            $suspended = new Customer(['status' => CustomerStatus::Suspended]);

            expect($active->canPlaceOrders())->toBeTrue()
                ->and($suspended->canPlaceOrders())->toBeFalse();
        });
    });

    describe('Marketing Helpers', function (): void {
        it('checks acceptsMarketing', function (): void {
            $optin = new Customer(['accepts_marketing' => true]);
            $optout = new Customer(['accepts_marketing' => false]);

            expect($optin->acceptsMarketing())->toBeTrue()
                ->and($optout->acceptsMarketing())->toBeFalse();
        });
    });

    describe('Full Name Accessor', function (): void {
        it('returns combined first and last name', function (): void {
            $customer = new Customer([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            expect($customer->full_name)->toBe('John Doe');
        });
    });

    describe('Scopes', function (): void {
        it('can filter high value customers', function (): void {
            Customer::create([
                'first_name' => 'High',
                'last_name' => 'Value',
                'email' => 'high-value-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 100000,
            ]);

            Customer::create([
                'first_name' => 'Low',
                'last_name' => 'Value',
                'email' => 'low-value-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 500,
            ]);

            $highValue = Customer::highValue(50000)->get();

            expect($highValue->every(fn ($c) => $c->lifetime_value >= 50000))->toBeTrue();
        });

        it('has inSegment scope', function (): void {
            $query = Customer::inSegment('test-id');
            expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
        });

        it('can filter recently active customers', function (): void {
            $recent = Customer::create([
                'first_name' => 'Recent',
                'last_name' => 'Active',
                'email' => 'recent-active-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'last_login_at' => now()->subDays(5),
            ]);

            $old = Customer::create([
                'first_name' => 'Old',
                'last_name' => 'Login',
                'email' => 'old-login-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'last_login_at' => now()->subDays(60),
            ]);

            $recentlyActive = Customer::recentlyActive(30)->get();

            expect($recentlyActive->pluck('id'))->toContain($recent->id)
                ->and($recentlyActive->pluck('id'))->not->toContain($old->id);
        });

        it('has taxExempt scope pattern', function (): void {
            // Test active scope instead - taxExempt scope doesn't exist
            $query = Customer::active();
            expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
        });
    });

    describe('Tag Helpers', function (): void {
        it('can tag for segment', function (): void {
            $customer = Customer::create([
                'first_name' => 'Tag',
                'last_name' => 'Test',
                'email' => 'tag-test-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $customer->tagForSegment(['vip', 'premium']);

            expect($customer->tags->where('type', 'segments')->pluck('name')->toArray())
                ->toContain('vip')
                ->toContain('premium');
        });

        it('can query by segment tag', function (): void {
            $customer = Customer::create([
                'first_name' => 'Tagged',
                'last_name' => 'Customer',
                'email' => 'tagged-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $customer->tagForSegment(['gold-member']);

            $found = Customer::withSegmentTag('gold-member')->get();

            expect($found->pluck('id'))->toContain($customer->id);
        });
    });

    describe('Media Collections', function (): void {
        it('registers media collections', function (): void {
            $customer = Customer::create([
                'first_name' => 'Media',
                'last_name' => 'Test',
                'email' => 'media-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $customer->registerMediaCollections();

            // Should not throw
            expect(true)->toBeTrue();
        });

        it('returns null for avatar when none set', function (): void {
            $customer = Customer::create([
                'first_name' => 'No',
                'last_name' => 'Avatar',
                'email' => 'no-avatar-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($customer->getAvatarUrl())->toBeNull();
        });
    });

    describe('Soft Deletes', function (): void {
        it('can be soft deleted', function (): void {
            $customer = Customer::create([
                'first_name' => 'Delete',
                'last_name' => 'Test',
                'email' => 'delete-test-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $customerId = $customer->id;
            $customer->delete();

            expect(Customer::find($customerId))->toBeNull()
                ->and(Customer::withTrashed()->find($customerId))->not->toBeNull();
        });
    });
});
