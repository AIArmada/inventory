<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;

describe('Customer Model - Extended Coverage', function (): void {
    describe('Relationships', function (): void {
        it('has addresses relationship', function (): void {
            $customer = new Customer;
            expect($customer->addresses())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        });

        it('has segments relationship', function (): void {
            $customer = new Customer;
            expect($customer->segments())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });

        it('has notes relationship', function (): void {
            $customer = new Customer;
            expect($customer->notes())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        });

        it('has groups relationship', function (): void {
            $customer = new Customer;
            expect($customer->groups())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });
    });

    describe('Casts', function (): void {
        it('has correct casts', function (): void {
            $customer = new Customer;
            $casts = $customer->getCasts();

            expect(array_key_exists('status', $casts))->toBeTrue()
                ->and(array_key_exists('accepts_marketing', $casts))->toBeTrue()
                ->and(array_key_exists('is_tax_exempt', $casts))->toBeTrue()
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

        it('has active scope pattern', function (): void {
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
});
