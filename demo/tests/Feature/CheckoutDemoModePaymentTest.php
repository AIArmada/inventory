<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout falls back to demo payment simulation when CHIP is not configured', function (): void {
    config()->set('chip.collect.api_key', null);
    config()->set('chip.collect.brand_id', null);

    /** @var \App\Models\User $owner */
    $owner = \App\Models\User::factory()->create();

    $product = OwnerContext::withOwner($owner, function (): Product {
        return Product::create([
            'name' => 'Demo Product',
            'sku' => 'DEMO-001',
            'price' => 10_00,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($product): void {
        PriceList::create([
            'name' => 'Retail',
            'slug' => 'retail',
            'currency' => 'MYR',
            'is_default' => true,
            'is_active' => true,
        ]);

        $priceList = PriceList::query()->firstOrFail();

        Price::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => $product->getMorphClass(),
            'priceable_id' => $product->getKey(),
            'amount' => 10_00,
            'currency' => 'MYR',
        ]);
    });

    /** @var \Tests\TestCase $this */
    $this->actingAs($owner);

    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    $response = $this->post(route('shop.checkout.process'), [
        'email' => 'demo-mode@example.com',
        'phone' => '+60123456789',
        'first_name' => 'Demo',
        'last_name' => 'Mode',
        'line1' => '1 Jalan Demo',
        'line2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'WP Kuala Lumpur',
        'postcode' => '50000',
        'shipping_method' => 'free',
        'payment_method' => 'fpx',
    ]);

    $order = OwnerContext::withOwner($owner, fn () => Order::query()->latest('created_at')->first());
    expect($order)->not()->toBeNull();

    $expectedRedirect = route('shop.payment.success', $order);
    $response->assertRedirect($expectedRedirect);

    expect($order->metadata['chip_purchase_id'] ?? null)
        ->toBe('demo-'.$order->order_number);
});
