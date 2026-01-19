<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('payment success simulates CHIP webhook without morph map violations', function (): void {
    /** @var \App\Models\User&\Illuminate\Contracts\Auth\Authenticatable $owner */
    $owner = \App\Models\User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::override($owner);

    $order = OwnerContext::withOwner($owner, function () use ($owner): Order {
        $order = Order::create([
            'order_number' => 'ORD-DEMO-' . Str::upper(Str::random(8)),
            'status' => 'pending_payment',
            'subtotal' => 10_00,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10_00,
            'currency' => 'MYR',
            'metadata' => [
                'chip_purchase_id' => 'demo-purchase-123',
            ],
        ]);

        if ($order->owner_id === null) {
            $order->assignOwner($owner);
            $order->save();
        }

        OrderAddress::create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => 'Demo',
            'last_name' => 'Buyer',
            'line1' => '1 Jalan Demo',
            'line2' => null,
            'city' => 'Kuala Lumpur',
            'state' => 'WP Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
            'phone' => '+60123456789',
            'email' => 'demo-buyer@example.com',
        ]);

        return $order;
    });

    /** @var \Tests\TestCase $this */
    $this->actingAs($owner)
        ->get(route('shop.payment.success', $order))
        ->assertOk();

    $order->refresh();

    expect($order->paid_at)->not()->toBeNull();
    expect($order->payments()->count())->toBeGreaterThan(0);

    OwnerContext::clearOverride();
});
