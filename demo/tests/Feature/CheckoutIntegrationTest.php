<?php

declare(strict_types=1);

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout uses pricing + tax + customers and remains owner-isolated', function (): void {
    config()->set('audit.enabled', true);
    config()->set('audit.console', true);
    config()->set('orders.audit.enabled', true);

    config()->set('chip.collect.api_key', 'test-api-key');
    config()->set('chip.collect.brand_id', 'test-brand-id');

    /** @var \App\Models\User $ownerA */
    $ownerA = \App\Models\User::factory()->create();

    /** @var \App\Models\User $ownerB */
    $ownerB = \App\Models\User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function (): Product {
        return Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $productB = OwnerContext::withOwner($ownerB, function (): Product {
        return Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-001',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $productA): void {
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
            'priceable_type' => $productA->getMorphClass(),
            'priceable_id' => $productA->getKey(),
            'amount' => 400_00,
            'currency' => 'MYR',
        ]);

        $zone = TaxZone::create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'priority' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'zone_id' => $zone->id,
            'tax_class' => 'standard',
            'name' => 'SST',
            'rate' => 600,
            'is_active' => true,
        ]);
    });

    $purchase = PurchaseData::from([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'checkout_url' => 'https://chip.test/checkout',
        'purchase' => [
            'total' => 0,
            'currency' => 'MYR',
            'products' => [],
        ],
        'client' => [],
    ]);

    $chipCollectService = \Mockery::mock(ChipCollectService::class);
    $chipCollectService->shouldReceive('createPurchase')->andReturn($purchase);

    Chip::shouldReceive('purchase')->andReturn(new PurchaseBuilder($chipCollectService));

    /** @var \Tests\TestCase $this */
    $this->actingAs($ownerA);

    $this->post(route('shop.cart.add'), [
        'product_id' => $productA->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('shop.cart.add'), [
        'product_id' => $productB->id,
        'quantity' => 1,
    ])->assertNotFound();

    $this->post(route('shop.checkout.process'), [
        'email' => 'buyer-a@example.com',
        'phone' => '+60123456789',
        'first_name' => 'A',
        'last_name' => 'Buyer',
        'line1' => '1 Jalan Demo',
        'line2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'WP Kuala Lumpur',
        'postcode' => '50000',
        'shipping_method' => 'free',
        'payment_method' => 'fpx',
    ])->assertRedirect('https://chip.test/checkout');

    $customerA = OwnerContext::withOwner($ownerA, fn () => Customer::query()->where('email', 'buyer-a@example.com')->first());
    expect($customerA)->not()->toBeNull();

    $orderA = OwnerContext::withOwner($ownerA, fn () => Order::query()->latest('created_at')->first());
    expect($orderA)->not()->toBeNull();

    $audit = \OwenIt\Auditing\Models\Audit::query()
        ->where('auditable_type', $orderA->getMorphClass())
        ->where('auditable_id', $orderA->id)
        ->latest('id')
        ->first();
    expect($audit)->not()->toBeNull();

    $tags = array_filter(array_map('trim', explode(',', (string) $audit->tags)));
    expect($tags)->toContain('commerce');
    expect($tags)->toContain('orders');

    expect($orderA->subtotal)->toBe(800_00);
    expect($orderA->tax_total)->toBe(48_00);
    expect($orderA->grand_total)->toBe(848_00);
    expect($orderA->customer_type)->toBe($customerA->getMorphClass());
    expect($orderA->customer_id)->toBe($customerA->id);

    OwnerContext::withOwner($ownerB, function () use ($orderA): void {
        $shouldBeNull = Order::query()->whereKey($orderA->id)->first();
        expect($shouldBeNull)->toBeNull();
    });
});
