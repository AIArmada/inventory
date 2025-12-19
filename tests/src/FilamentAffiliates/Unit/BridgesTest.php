<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;
use AIArmada\FilamentCart\Models\Cart as SnapshotCart;
use AIArmada\FilamentVouchers\Models\Voucher;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::dropIfExists('cart_snapshots');
    Schema::create('cart_snapshots', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_key')->default('global');
        $table->string('identifier');
        $table->string('instance')->default('default');
        $table->json('items')->nullable();
        $table->json('conditions')->nullable();
        $table->json('metadata')->nullable();
        $table->unsignedInteger('items_count')->default(0);
        $table->unsignedInteger('quantity')->default(0);
        $table->bigInteger('subtotal')->default(0);
        $table->bigInteger('total')->default(0);
        $table->bigInteger('savings')->default(0);
        $table->string('currency', 3)->default('USD');

        // AI/Analytics columns
        $table->timestamp('last_activity_at')->nullable();
        $table->timestamp('checkout_started_at')->nullable();
        $table->timestamp('checkout_abandoned_at')->nullable();
        $table->unsignedTinyInteger('recovery_attempts')->default(0);
        $table->timestamp('recovered_at')->nullable();

        // Collaborative Cart Support
        $table->boolean('is_collaborative')->default(false);
        $table->unsignedSmallInteger('collaborator_count')->default(0);

        $table->timestamps();
    });
});

test('cart bridge resolves urls when integration enabled', function (): void {
    $cart = SnapshotCart::create([
        'identifier' => 'bridge-user',
        'instance' => 'default',
        'subtotal' => 100,
        'total' => 90,
    ]);

    $url = app(CartBridge::class)->resolveUrl('bridge-user');

    expect($cart)->toBeInstanceOf(SnapshotCart::class)
        ->and($url)->not()->toBeNull();
});

test('cart bridge honours config toggle', function (): void {
    config(['filament-affiliates.integrations.filament_cart' => false]);

    expect(app(CartBridge::class)->isAvailable())->toBeFalse();

    config(['filament-affiliates.integrations.filament_cart' => true]);
});

test('cart bridge does not resolve urls when cart is not referenced in current owner scope', function (): void {
    config([
        'affiliates.owner.enabled' => true,
        'affiliates.owner.auto_assign_on_create' => false,
    ]);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'cart-owner-a-' . Str::uuid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'cart-owner-b-' . Str::uuid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $cart = SnapshotCart::create([
        'identifier' => 'collision-cart',
        'instance' => 'default',
        'subtotal' => 100,
        'total' => 90,
    ]);

    $affiliateB = Affiliate::create([
        'code' => 'AFF-B-' . Str::uuid(),
        'name' => 'Affiliate B',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'cart_identifier' => $cart->identifier,
        'cart_instance' => $cart->instance,
        'order_reference' => 'ORDER-B-1',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
        'occurred_at' => now(),
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    expect(app(CartBridge::class)->resolveUrl($cart->identifier, $cart->instance))->toBeNull();

    $affiliateA = Affiliate::create([
        'code' => 'AFF-A-' . Str::uuid(),
        'name' => 'Affiliate A',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'cart_identifier' => $cart->identifier,
        'cart_instance' => $cart->instance,
        'order_reference' => 'ORDER-A-1',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
        'occurred_at' => now(),
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    expect(app(CartBridge::class)->resolveUrl($cart->identifier, $cart->instance))->not()->toBeNull();
});

test('voucher bridge resolves urls for vouchers when enabled', function (): void {
    $voucher = Voucher::create([
        'code' => 'BRIDGE-VOUCHER',
        'name' => 'Bridge Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
    ]);

    $url = app(VoucherBridge::class)->resolveUrl('BRIDGE-VOUCHER');

    expect($voucher->code)->toBe('BRIDGE-VOUCHER')
        ->and($url)->not()->toBeNull();
});

test('voucher bridge does not resolve urls outside current owner scope', function (): void {
    config([
        'vouchers.owner.enabled' => true,
        'vouchers.owner.include_global' => false,
    ]);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => bcrypt('password'),
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => bcrypt('password'),
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Voucher::create([
        'code' => 'BRIDGE-VOUCHER-OTHER',
        'name' => 'Other Owner Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    expect(app(VoucherBridge::class)->resolveUrl('BRIDGE-VOUCHER-OTHER'))->toBeNull();

    Voucher::create([
        'code' => 'BRIDGE-VOUCHER-OWNED',
        'name' => 'Owned Voucher',
        'type' => VoucherType::Fixed,
        'value' => 700,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    expect(app(VoucherBridge::class)->resolveUrl('BRIDGE-VOUCHER-OWNED'))->not()->toBeNull();
});

test('voucher bridge can be disabled via config', function (): void {
    config(['filament-affiliates.integrations.filament_vouchers' => false]);

    expect(app(VoucherBridge::class)->isAvailable())->toBeFalse();

    config(['filament-affiliates.integrations.filament_vouchers' => true]);
});
