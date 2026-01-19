<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout displays pre-discount subtotal when a voucher is applied', function (): void {
	$owner = \App\Models\User::factory()->create([
		'email' => 'admin@commerce.demo',
	]);

	OwnerContext::override($owner);

	$product = Product::create([
		'name' => 'AirPods Pro',
		'sku' => 'APP-2-001',
		'price' => 109_900,
		'currency' => 'MYR',
		'status' => ProductStatus::Active,
	]);

	$product->assignOwner($owner)->save();

	Voucher::create([
		'code' => 'LOYAL100-TEST',
		'name' => 'RM 100 off',
		'description' => null,
		'type' => VoucherType::Fixed,
		'value' => 10_000,
		'currency' => 'MYR',
		'min_cart_value' => 20_000,
		'max_discount' => null,
		'usage_limit' => null,
		'usage_limit_per_user' => null,
		'applied_count' => 0,
		'allows_manual_redemption' => true,
		'starts_at' => null,
		'expires_at' => null,
		'status' => VoucherStatus::Active,
		'target_definition' => null,
		'metadata' => null,
		'owner_type' => $owner->getMorphClass(),
		'owner_id' => (string) $owner->getKey(),
	]);

	/** @var \Tests\TestCase $this */
	$this->post(route('shop.cart.add'), [
		'product_id' => $product->id,
		'quantity' => 2,
	])->assertRedirect();

	$this->post(route('shop.cart.voucher'), [
		'voucher_code' => 'LOYAL100-TEST',
	])->assertRedirect();

	$response = $this->get(route('shop.checkout'));

	$response->assertOk();

	$html = (string) $response->getContent();

	expect($html)->toMatch('/<span>\s*Subtotal\s*<\/span>\s*<span>\s*RM\s*2,198\.00\s*<\/span>/');
	expect($html)->not()->toContain('voucher_LOYAL100-TEST');

	OwnerContext::clearOverride();
});
