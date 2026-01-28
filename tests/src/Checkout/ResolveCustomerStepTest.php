<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\ResolveCustomerStep;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Customers\Models\Customer;

describe('ResolveCustomerStep', function (): void {
    it('creates a guest customer from billing and shipping data', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'cart-guest-1',
            'billing_data' => [
                'email' => 'guest@example.com',
                'first_name' => 'Guest',
                'last_name' => 'User',
                'line1' => '123 Guest Lane',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            'shipping_data' => [
                'email' => 'guest@example.com',
                'name' => 'Guest User',
                'line1' => '456 Shipping Road',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]);

        $step = app(ResolveCustomerStep::class);
        $step->handle($session);

        $session->refresh();
        $customer = Customer::find($session->customer_id);

        expect($customer)->not->toBeNull()
            ->and($customer->is_guest)->toBeTrue()
            ->and($customer->email)->toBe('guest@example.com')
            ->and($customer->addresses()->count())->toBe(2);
    });

    it('merges a guest customer into an authenticated customer', function (): void {
        $user = User::factory()->create([
            'email' => 'registered@example.com',
        ]);

        $userCustomer = Customer::create([
            'user_id' => $user->id,
            'first_name' => 'Registered',
            'last_name' => 'User',
            'email' => 'registered@example.com',
            'is_guest' => false,
        ]);

        $guestCustomer = Customer::create([
            'first_name' => 'Guest',
            'last_name' => 'Checkout',
            'email' => 'guest@example.com',
            'is_guest' => true,
        ]);

        $guestCustomer->addresses()->create([
            'type' => 'billing',
            'line1' => '789 Merge Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country' => 'MY',
            'is_default_billing' => true,
        ]);

        $session = CheckoutSession::create([
            'cart_id' => 'cart-merge-1',
            'customer_id' => $guestCustomer->id,
            'billing_data' => [
                'email' => 'registered@example.com',
                'first_name' => 'Registered',
                'last_name' => 'User',
                'line1' => '789 Merge Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]);

        $this->actingAs($user);

        $step = app(ResolveCustomerStep::class);
        $step->handle($session);

        $session->refresh();

        expect($session->customer_id)->toBe($userCustomer->id)
            ->and(Customer::query()->whereKey($guestCustomer->id)->exists())->toBeFalse()
            ->and($userCustomer->addresses()->count())->toBe(1);
    });
});
